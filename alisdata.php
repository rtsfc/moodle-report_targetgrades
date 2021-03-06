<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Import ALIS statistics for target grade calculations
 *
 * Presents a form allowing a CSV file containing ALIS data (generated using
 * alis_pdf2csv.sh) to be uploaded. Once uploaded, a list of statistics is
 * displayed, allowing patterns to be defined for each set of statistics to be
 * applied to when grades are distributed.
 * 
 * @package report
 * @subpackage targetgrades
 * @author      Mark Johnson <mark.johnson@tauntons.ac.uk>
 * @copyright   2011 Tauntons College, UK
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/report/targetgrades/alisdata_form.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/report/targetgrades/lib.php');
require_once($CFG->libdir.'/adminlib.php');

use report\targetgrades as tg;

require_login($SITE);
admin_externalpage_setup('reporttargetgrades', null, null, '/'.$CFG->admin.'/report/targetgrades/alisdata.php');
$PAGE->navbar->add(get_string('alisdata', 'report_targetgrades'));

$renderer = $PAGE->get_renderer('report_targetgrades');

$savepatterns = optional_param('savepatterns', false, PARAM_BOOL);
$alispatterns = optional_param('alispatterns', array(), PARAM_CLEAN);
$addpattern = optional_param('addpattern', array(), PARAM_CLEAN);
$config = tg\get_config('report_targetgrades');
$addfield = optional_param('addfield', null, PARAM_INT);

### @export 'pattern_process'
if ($savepatterns || !empty($addpattern)) {
    foreach($alispatterns as $alisid => $patterns) {
        foreach($patterns as $id => $pattern) {
            if ($alisdata = $DB->get_record('report_targetgrades_alisdata', array('id' => $alisid))) {
                if($patternrecord = $DB->get_record('report_targetgrades_patterns', array('id' => $id))) {
                    if(empty($pattern)) {
                        $DB->delete_records('report_targetgrades_patterns', array('id' => $id));
                    } else {
                        $patternrecord->pattern = $pattern;
                        $DB->update_record('report_targetgrades_patterns', $patternrecord);
                    }
                } else if (!empty($pattern)) {
                    $patternrecord = new stdClass;
                    $patternrecord->alisdataid = $alisid;
                    $patternrecord->pattern = $pattern;
                    $patternrecord->id = $DB->insert_record('report_targetgrades_patterns', $patternrecord);
                }
            }
        }
    }
    $output = '<p>'.get_string('changessaved').'</p>';
    if (!empty($addpattern)) {
        $params = array('addfield' => key($addpattern));
        redirect(new moodle_url('/admin/report/targetgrades/alisdata.php#alis'.key($addpattern), $params), '', 0);
    }
}

### @export 'uploadform'
$uploadform = new alisdata_upload_form();
$uploaddata = $uploadform->get_data();

if ($uploaddata) {
    $handler = new tg\csvhandler($uploaddata->equationsfile);
    $handler->validate();
    $import = $handler->process();

    $output = '<p>'.get_string('importoutput', 'report_targetgrades', $import).'</p>';

}

### @export 'table'
$select = 'SELECT a.*, a.name AS subject, q.name AS qualification ';
$from = 'FROM {report_targetgrades_alisdata} a
    JOIN {report_targetgrades_qualtype} q ON a.qualtypeid = q.id ';
$order = 'ORDER BY q.name, a.name ASC';

if($alis_data = $DB->get_records_sql($select.$from.$order)) {

    $table = new html_table();

    $helpicon = $OUTPUT->help_icon('col_quality', 'report_targetgrades');
    $table->head = array(get_string('col_qualtype', 'report_targetgrades'),
            get_string('col_name', 'report_targetgrades'),
            get_string('col_pattern', 'report_targetgrades'),
            get_string('col_gradient', 'report_targetgrades'),
            get_string('col_intercept', 'report_targetgrades'),
            get_string('col_quality', 'report_targetgrades').$helpicon);

### @export 'table_patterns'
    try {
        $options = tg\build_pattern_options();
    } catch (unsafe_regex_exception $e) {
        print_error($e->getMessage(), 'report_targetgrades');
    }

### @export 'table_loop'
    foreach($alis_data as $alis) {
        $form = '';
### @export 'table_patternselector'
        if($patterns = $DB->get_records('report_targetgrades_patterns', array('alisdataid' => $alis->id))) {
            $break = 1;
            foreach ($patterns as $pattern) {
                $optionswithpattern = array_merge($options, array($pattern->pattern => $pattern->pattern));
                asort($optionswithpattern);
                $selectname = 'alispatterns['.$alis->id.']['.$pattern->id.']';
                $form .= $renderer->datalist($optionswithpattern, $selectname, $pattern->pattern);
                if((count($patterns) > 1 && $break < count($patterns)) || $addfield == $alis->id) {
                    $form .= html_writer::empty_tag('br');
                }
                $break++;
            }

            if ($addfield == $alis->id) {
                $form .= $renderer->datalist($options, 'alispatterns['.$alis->id.'][]');
            }
        } else {
            $form .= $renderer->datalist($options, 'alispatterns['.$alis->id.'][]');
        }

        $attrs = array('type' => 'submit', 
                        'value' => '+', 
                        'name' => 'addpattern['.$alis->id.']', 
                        'title' => get_string('saveandadd', 'report_targetgrades'));
        $form .= html_writer::empty_tag('input', $attrs);

### @export 'table_quality'
        $quality = array();
        $quality_samplesize = (object)array('field' => 'samplesize', 'display' => 'S');
        switch ($alis->quality_samplesize) {
            case 1:
                $quality_samplesize->class = 'ok';
                $quality_samplesize->message = 'oksize';
                $quality[] = $quality_samplesize;
                break;
            case 2:
                $quality_samplesize->class = 'low';
                $quality_samplesize->message = 'lowsize';
                $quality[] = $quality_samplesize;
                break;
            case 3:
                $quality_samplesize->class = 'vlow';
                $quality_samplesize->message = 'vlowsize';
                $quality[] = $quality_samplesize;
                break;
        }

        if($alis->quality_correlation) {
                $quality[] = (object)array('field' => 'correlation', 
                                            'message' => 'lowcorrelation', 
                                            'class' => 'low', 
                                            'display' => 'C');
        }

        $quality_deviation = (object)array('field' => 'standarddeviation', 'display' => 'D');
        switch ($alis->quality_deviation) {
            case 1:
                $quality_deviation->class = 'low';
                $quality_deviation->message = 'highdeviation';
                $quality[] = $quality_deviation;
                break;
            case 2:
                $quality_deviation->class = 'vlow';
                $quality_deviation->message = 'vhighdeviation';
                $quality[] = $quality_deviation;
                break;
        }
        
        $quality_html = array();
        if (!empty($quality)) {
            foreach($quality as $status) {
                $field = $status->field;
                $class = 'tg_'.$status->class.'quality';
                $title = get_string($status->message, 'report_targetgrades', $alis->$field);
                $quality_html[] = html_writer::tag('abbr', $status->display, array('class' => $class, 'title' => $title));
            }
        } else {
            $src = $OUTPUT->pix_url('i/tick_green_big');
            $title = get_string('okquality', 'report_targetgrades');
            $quality_html[] = html_writer::empty_tag('img', array('src' => $src, 'title' => $title));
        }

### @export 'table_row'
        $row = new html_table_row;
        $row->cells[] = $alis->qualification;
        $row->cells[] = html_writer::tag('a', $alis->subject, array('name' => 'alis'.$alis->id));

        $row->cells[] = $form;
        $row->cells[] = $alis->gradient;
        $row->cells[] = $alis->intercept;
        $row->cells[] = implode('', $quality_html);
        $table->data[] = $row;
    }
}
$PAGE->requires->js_init_call('M.report_targetgrades.init_datalist');

### @export 'output'
echo $OUTPUT->header();
tg\print_tabs(1);

echo html_writer::tag('h2', get_string('alisdata', 'report_targetgrades'));
echo html_writer::tag('p', get_string('configalis', 'report_targetgrades'));
if(isset($output)) {
    echo $output;
}
$uploadform->display();
if(isset($table)) {
    echo html_writer::tag('p', get_string('explainpatterns', 'report_targetgrades', $config));
    echo html_writer::start_tag('form', array('action' => $PAGE->url->out(), 'method' => 'post'));
    echo html_writer::table($table);
    $attrs = array('type' => 'submit', 'name' => 'savepatterns', 'value' => get_string('savechanges'));
    echo html_writer::empty_tag('input', $attrs);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
### @end
?>
