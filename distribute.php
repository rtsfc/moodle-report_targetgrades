<?php
/**
 * Distribute Minimum target grades
 * 
 * Displays a Big Select List to allow selection of courses for grades to be 
 * distributed to. 
 * When the form is submitted, each course has the 4 required
 * grade items created if necessary, and the grades for each student are
 * calculated and entered. The gradebook is then re-sorted to move the target 
 * grade items to the front.
 *
 * @package block_mtgdistribute
 * @author Mark Johnson <johnsom@tauntons.ac.uk>
 * @copyright Taunton's College, Southampton, UK 2010
 */

require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/blocks/mtgdistribute/lib.php');
set_time_limit(0); // This could take a while, so disable max execution time

$context = get_context_instance(CONTEXT_SYSTEM);
if (!has_capability('block/mtgdistribute:distribute', $context)){
    print_error('noperms', 'block_mtgdistribute');
}

$defaultscale = optional_param('defaultscale', null, PARAM_INT);
if (!empty($defaultscale)) {
    set_config('defaultscale', $defaultscale, 'block/mtgdistribute');
}

$config = get_config('block/mtgdistribute'); // Get the raw config data for the block

if(preg_match('/(.+?\.?[*+].*?)[*+]/', $config->exclude_regex)) {
    print_error('unsaferegex', 'block_mtgdistribute');
}

$config->selected = explode(',', $config->selected); // Get the list of selected programmes

if(empty($config->selected[0])) {
    // If there are no selected programmes, get the programmes where there are already MTG fields distributed
    $select = 'SELECT c.* ';
    
    $from = sprintf('FROM
                %1$scourse AS c,
                %1$sgrade_items AS g ', $CFG->prefix);

    $args = array(get_string('item_avgcse', 'block_mtgdistribute'),
                get_string('item_alisnum', 'block_mtgdistribute'),
                get_string('item_alis', 'block_mtgdistribute'),
                get_string('item_mtg', 'block_mtgdistribute'));
    $where = vsprintf('WHERE c.id = g.courseid
                AND itemname IN ("%1$s", "%2$s", "%3$s", "%4$s")', $args);

    if($distributed = get_records_sql($select.$from.$where)) {
        $config->selected = array();
        foreach ($distributed as $record) {
            $config->selected[] = $record->id;
        }
        mtgdistribute_saveselected($config->selected, 'add'); // Add these programmes to the selected list
    }
}

$courses = mtgdistribute_get_courses_with_qualtype(); // Get a list of all courses matching the preferences

$unselectedcourses = $courses; // Remove any courses that aren't a qualification tracked in this year's ALIS
array_walk($courses, 'mtgdistribute_hasconfig'); // Mark all courses that don't have any ALIS data

$distribute = optional_param('distribute', null, PARAM_TEXT);
if (!empty($distribute)) { // If the distribute button has been clicked,    
    $output = '';

    $itemnames = array('mtg'   =>  get_string('item_mtg', 'block_mtgdistribute'),
                        'alis'  =>  get_string('item_alis', 'block_mtgdistribute'),
                        'alisnum' =>    get_string('item_alisnum', 'block_mtgdistribute'),
                        'avgcse' => get_string('item_avgcse', 'block_mtgdistribute'),
                        'cpg' => get_string('item_cpg', 'block_mtgdistribute'));
    $empty_courses = array();
    $unconfigured_courses = array();
    $empty_students = array();
    $failed_grade_calcs = array();
    $errors = '';
    $infofield = get_record('user_info_field', 'shortname', $config->gcse_field);

    foreach($config->selected as $courseid) {
        if($course = mtgdistribute_get_course_with_qualtype($courseid)) {
            $category = grade_category::fetch_course_category($course->id);

            $records = null;

            $regrade = false;
            foreach ($itemnames as $item => $itemname) {
                try {
                    if($grade_item = grade_item::fetch(array('idnumber'=>'alis_'.$item, 'courseid'=>$course->id))) {
                        $itemdata = new stdClass();
                        if(empty($grade_item->timecreated)) {
                            $itemdata->timecreated = time();
                        }
                        if(empty($grade_item->itemnumber)) {
                            $itemdata->itemnumber = 0;
                        }
                        grade_item::set_properties($grade_item, $itemdata);
                        $grade_item->update();
                        unset($itemdata);
                        throw new grade_item_exists_exception($item, $grade_item->id);
                    }

                    $itemclass = 'mtg_item_'.$item;
                    $itemdata = new $itemclass($course->id, $category->id);
                    if (in_array($item, array('mtg', 'alis', 'cpg'))) {
                        try {
                            $itemdata->set_scale($course->qualtype, $defaultscale);
                        } catch (Exception $e) {
                            $failed_grade_calcs++;
                            $errors .= get_string('nogradescale', 'block_mtgdistribute', $e->getMessage()).'<br />';
                        }
                    }

                    $grade_item = new grade_item(array('courseid'=>$course->id, 'itemtype'=>'manual'), false);
                    grade_item::set_properties($grade_item, $itemdata);
                    $itemids[$item] = $grade_item->insert();
                    $regrade = true;

                } catch (grade_item_exists_exception $e) {
                    $itemids[$e->getMessage()] = $e->getId();
                }

            }

            // Get the ID and average GCSE score of all students in the course

            $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

            $select = 'SELECT u.id AS id ';

            $from = sprintf('FROM %1$suser as u
                    JOIN %1$srole_assignments as ra
                        ON u.id = ra.userid ', $CFG->prefix);

            $args = array($config->roles, $coursecontext->id);
            $where = vsprintf('WHERE u.deleted = 0
                        AND ra.roleid IN (%1$s)
                        AND ra.contextid = %2$s', $args);

            try {
                $students = get_records_sql($select.$from.$where);

                if(!$students) {
                    throw new no_students_exception($course->id);
                }

                if (!isset($course->pattern)) {
                    throw new no_config_for_course_exception($course->id);
                }

                // If there are some students in the class
                foreach($students as $student) {

                    if($data = get_record('user_info_data', 'fieldid', $infofield->id, 'userid', $student->id)) {
                        $student->{$config->gcse_field} = $data->data;
                    } else {
                        $student->{$config->gcse_field} = '';
                    }

                    try {
                        if(empty($student->{$config->gcse_field})) {
                            throw new no_data_for_student_exception($student->id);
                        }

                        if($avgcse = grade_grade::fetch(array('itemid' => $itemids['avgcse'], 'userid' => $student->id))) {
                            // If they've already got an average gcse grade, update it
                            $avgcse->rawgrade = $student->avgcse;
                            $avgcse->finalgrade = $student->avgcse;
                            $avgcse->timemodified = time();
                            $avgcse->update('mtgdistribute');
                        } else {
                            // Otherwise, create it
                            //$gradedata = new mtg_grade($itemids['avgcse'], $student, $student->avgcse);
                            $avgcse = new grade_grade();
                            $avgcse->itemid = $itemids['avgcse'];
                            $avgcse->userid = $student->id;
                            $avgcse->rawgrade = $student->avgcse;
                            $avgcse->finalgrade = $student->avgcse;
                            $avgcse->timecreated = time();
                            $avgcse->timemodified = time();
                            $avgcse->insert('mtgdistribute');
                        }

                        $mtg = mtgdistribute_calculate_mtg($student, $course);

                        if($alis = grade_grade::fetch(array('itemid' => $itemids['alis'], 'userid' => $student->id))) {
                            $alis->rawgrade = $mtg['grade'];
                            $alis->finalgrade = $mtg['grade'];
                            $alis->timemodified = time();
                            $alis->update('mtgdistribute');
                        } else {
                            $alis = new grade_grade();
                            $alis->itemid = $itemids['alis'];
                            $alis->userid = $student->id;
                            $alis->rawgrade = $mtg['grade'];
                            $alis->finalgrade = $mtg['grade'];
                            $alis->timecreated = time();
                            $alis->timemodified = time();
                            $alis->insert('mtgdistribute');
                        }

                        if($alis_num = grade_grade::fetch(array('itemid' => $itemids['alisnum'], 'userid' => $student->id))){
                            $alis_num->rawgrade = $mtg['number'];
                            $alis_num->finalgrade = $mtg['number'];
                            $alis_num->timemodified = time();
                            $alis_num->update('mtgdistribute');
                        } else {
                            $alis_num = new grade_grade();
                            $alis_num->itemid = $itemids['alisnum'];
                            $alis_num->userid = $student->id;
                            $alis_num->rawgrade = $mtg['number'];
                            $alis_num->finalgrade = $mtg['number'];
                            $alis_num->timecreated = time();
                            $alis_num->timemodified = time();
                            $alis_num->insert('mtgdistribute');
                        }

                    } catch (no_data_for_student_exception $e) {
                        $empty_students[] = $e->getMessage();
                    } catch (no_mtg_for_student_exception $e) {
                        $failed_grade_calcs[] = $e->getMessage();
                    }

                }

            } catch (no_students_exception $e) {
                $empty_courses[] = $e->getMessage();
            } catch (no_config_for_course_exception $e) {
                $unconfigured_courses[] = $e->getMessage();
            }
            if($regrade) {
                grade_regrade_final_grades($courseid);
                mtgdistribute_sort_gradebook($course);
            }
        }
    }

    $output = '<p>'.
    get_string('distribute_success', 'block_mtgdistribute', count($config->selected)-count($empty_courses)-count($unconfigured_courses)).
    '<br />'.
    get_string('distribute_empty', 'block_mtgdistribute', count($empty_courses)).
    '<br />'.
    get_string('distribute_unconfigured', 'block_mtgdistribute', count($unconfigured_courses)).
    '<br />'.
    get_string('distribute_noavgcse', 'block_mtgdistribute', count(array_unique($empty_students))).
    '<br />'.
    get_string('distribute_failedcalc', 'block_mtgdistribute', count($failed_grade_calcs)).
    '<br />'.$errors.
    '</p>';

    $selectedcourses = array();
    foreach ($config->selected as $value) {
        $selectedcourses[$value] = $unselectedcourses[$value];
    }
    $unselectedcourses = array_diff_key($unselectedcourses, $selectedcourses);

} else {
   
    $selectedcourses = array();
    $add = optional_param('add', null, PARAM_TEXT);
    $remove = optional_param('remove', null, PARAM_TEXT);
    if(!empty($add)) {
        $selected = optional_param('addselect', array(), PARAM_RAW);
        if(!empty($selected)) {
            mtgdistribute_saveselected($selected, 'add');
            foreach ($selected as $value) {
                $selectedcourses[$value] = $unselectedcourses[$value];
            }
        }
        if(!empty($config->selected[0])) {
            foreach ($config->selected as $value) {
                $selectedcourses[$value] = $unselectedcourses[$value];
            }
        }
        $unselectedcourses = array_diff_key($unselectedcourses, $selectedcourses);
    } else if(!empty($remove)) {
        $selected = optional_param('removeselect', array(), PARAM_RAW);
        if (!empty($selected)) {
            $config->selected = array_diff($config->selected, $selected);
            mtgdistribute_saveselected($selected, 'remove');
        }
        if(!empty($config->selected)) {
            foreach ($config->selected as $value) {
                $selectedcourses[$value] = $unselectedcourses[$value];
            }
        }
    } else {
        mtgdistribute_clearselected();
        if(!empty($config->selected[0])) {
            foreach ($config->selected as $value) {
                $selectedcourses[$value] = $unselectedcourses[$value];
            }
        }
        $unselectedcourses = array_diff_key($unselectedcourses, $selectedcourses);
    }

}

$navlinks = array();
$navlinks[] = array('name' => get_string('mtgs', 'block_mtgdistribute'),
                    'type' => 'misc');
$navlinks[] = array('name' => get_string('mtgdistribute', 'block_mtgdistribute'),
                    'type' => 'misc');
$nav = build_navigation($navlinks);
print_header_simple(get_string('mtgdistribute', 'block_mtgdistribute'), get_string('mtgdistribute', 'block_mtgdistribute'), $nav);
mtgdistribute_print_tabs(2);
?>

<h2><?php get_string('mtgdistribute', 'block_mtgdistribute') ?></h2>
<form id="distributeform" method="post" action="distribute.php">
    <label for="defaultscale"><?php echo get_string('defaultscale', 'block_mtgdistribute'); ?></label>
    <?php
        $scales = get_records('scale');
    ?>
    <select name="defaultscale">
        <option value=""></option>
        <?php
            foreach($scales as $scale) {
                if (isset($config->defaultscale) && $scale->id == $config->defaultscale) {
                    $selected = ' selected="selected"';
                } else {
                    $selected = '';
                }
                $args = array($scale->id, $scale->name.' ('.$scale->scale.')', $selected);
                vprintf('<option value="%1$d"%3$s>%2$s</option>', $args);
            }
        ?>
    </select><br />
    <?php
        print_string('defaultscaledesc', 'block_mtgdistribute');
    ?>
<div style="text-align:center;">

  <table summary="" style="margin-left:auto;margin-right:auto" border="0" cellpadding="5" cellspacing="0">
    <tr>
      <td valign="top">
          <select name="removeselect[]" size="20" id="removeselect" multiple="multiple">

          <?php
            $i = 0;
            foreach ($selectedcourses as $course) {
                if (!empty($course)) {
                    echo '<option value="'.$course->id.'">'.$course->shortname.': '.$course->fullname.'</option>'."\n";
                    $i++;
                }
            }
            if ($i==0) {
                echo '<option/>'; // empty select breaks xhtml strict
            }
          ?>

          </select><br>
            <input type="text" id="removeselect_search" onkeyup="removesearch()"><br>
            <input type="submit" name="distribute" value="<?php print_string('distributegrades','block_mtgdistribute');?>" />

          </td>
      <td valign="top">
        <br />

        <?php check_theme_arrows(); ?>
        <p class="arrow_button">
            <input name="add" id="add" type="submit" value="<?php echo '&nbsp;&nbsp;&nbsp;'.$THEME->larrow.'&nbsp;'.get_string('add'); ?>" title="<?php print_string('add'); ?>" /><br />
            <input name="remove" id="remove" type="submit" value="<?php echo '&nbsp;&nbsp;&nbsp;'.get_string('remove'). '&nbsp;'.$THEME->rarrow.'&nbsp;'; ?>" title="<?php print_string('remove'); ?>" />
        </p>
      </td>
      <td valign="top">
          <select name="addselect[]" size="20" id="addselect" multiple="multiple">
       <?php
            $i = 0;
            foreach ($unselectedcourses as $course) {
                echo '<option value="'.$course->id.'">'.$course->shortname.': '.$course->fullname.'</option>'."\n";
                $i++;
            }
            if ($i==0) {
                echo '<option/>'; // empty select breaks xhtml strict
            }
          ?>
            </select><br>
            <input type="text" id="addselect_search" onkeyup="addsearch()"><br />
            <?php print_string('noalis', 'block_mtgdistribute'); ?>
       </td>
    </tr>
  </table>
</div>
</form>
<?php
if(!empty($output)) {
    echo $output;
}
?>
<script type="text/javascript">
function addsearch(){
    searchbox = document.getElementById('addselect_search');
    searchstring= new RegExp(searchbox.value,'gi');
    var list = document.getElementById('addselect');
    var i;
    for (i = list.length - 1; i>=0; i--) {
        if((list.options[i].text.search(searchstring) == -1)&&(searchbox.value!='')){
            list.options[i].style.display="none";
            list.appendChild(list.options[i]);//put to end so that not caught by shift-click
        }else{
            list.options[i].style.display="block";
        }

   }
}
function removesearch(){
    searchbox = document.getElementById('removeselect_search');
    searchstring= new RegExp(searchbox.value,'gi');
    var list = document.getElementById('removeselect');
    var i;
    for (i = list.length - 1; i>=0; i--) {
        if((list.options[i].text.search(searchstring) == -1)&&(searchbox.value!='')){
            list.options[i].style.display="none";
            list.appendChild(list.options[i]);//put to end so that not caught by shift-click
        }else{
            list.options[i].style.display="block";
        }

   }
}
</script>

<?php

print_footer();


?>
