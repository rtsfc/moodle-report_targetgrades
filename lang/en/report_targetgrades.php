<?php
$string['alisdata'] = 'Upload ALIS data';
$string['categories'] = 'Categories for Distribution';
$string['categoriesdesc'] = 'Only courses checked will be available for MTG distribution. Disable those you don\'t need to make the list more manageable';
$string['col_name'] = 'Course Name';
$string['col_pattern'] = 'Apply stats to pattern';
$string['col_gradient'] = 'Gradient';
$string['col_intercept'] = 'Intercept';
$string['col_qualtype'] = 'Qualification';
$string['configtitle'] = 'Configure Distribute Minimum Target Grades Block';
$string['configalis'] = 'ALIS (Advanced Level Information System) data is used to calculate minimum target grades for students based on their average GCSE score and past statistics for the relevant course.<br />
                        Latest data can be downloaded <a href=\"https://css.cemcentre.org/ALIS/Site/reports/default.aspx?reptype=6\">here</a> in PDF format. This must be converted to CSV format using the script included with this block before uploading.';
$string['configgradient'] = 'Gradient';
$string['configintercept'] = 'Intercept';
$string['createdgis'] = 'Grade items created';
$string['equationsfile'] = 'ALIS Equations file';
$string['equationsfiledesc'] = 'This MUST be a CSV file produced using alis_pdf2csv.sh, included with this block';
$string['exclude_field'] = 'Exclude courses where:';
$string['exclude_regex'] = 'matches pattern:';
$string['exclude_regexdesc'] = 'Must be a valid Regular Expression. Any courses matching this pattern will be excluded, and will NOT be available for distribution.<br />Use this to make the distribution list more manageable.';
$string['explainpatterns'] = 'A list of patterns has been generated based on the crietria defined in the block\'s settings, and the courses currently on the system. Selecting a pattern for a row in the table will apply those statistics to courses matching that pattern. Currently the pattern matches the first $a->group_length characters of $a->group_field';
$string['defaultscale'] = 'Default grade scale';
$string['defaultscaledesc'] = 'This scale will be applied to grade items in all courses with no configured ALIS Data. They can be changed after distribution in each course\'s gradebook';
$string['distribute_success'] = 'Grade items distributed to $a courses successfully';
$string['distribute_empty'] = '$a courses were ignored, becuase they have no students';
$string['distribute_unconfigured'] = '$a courses were ignored, becuase they have no ALIS data';
$string['distribute_noavgcse'] = '$a students were ignored, because they have no Average GCSE score';
$string['distribute_failedcalc'] = '$a grade calculations failed.';
$string['gcse_field'] = 'Average GCSE field';
$string['gcse_fielddesc'] = 'Select the user profile field where the user\'s average GCSE score is stored (as a number from 0-8).';
$string['gradesentered'] = 'Grades Entered';
$string['group_length'] = 'Group Courses By:';
$string['group_field'] = 'characters of';
$string['group_fielddesc'] = 'These settings allow you to apply a set of ALIS statistics to all courses that match the specified pattern. For example, you may have 3 classes in each A level which share the first 5 characters of their shortname.';
$string['item_avgcse'] = 'Average GCSE';
$string['item_alisnum'] = 'ALIS Number';
$string['item_alis'] = 'Minimum Grade';
$string['item_cpg'] = 'Current Performance Grade';
$string['item_mtg'] = 'Target Grade';
$string['importoutput'] = 'Imported $a->qualcount new qualification types, and $a->subjectcount new subjects. Updated $a->updatecount subjects.';
$string['mtgs'] = 'Minimum Target Grades';
$string['mtgdistribute'] = 'Distribute Target Grades';
$string['needsconfig'] = 'This block is not configured. You must select at least a gcse_field, roles and categories on the block\'s settings page.';
$string['needsalis'] = 'You must at least import ALIS data before you can distrbute grades.';
$string['noconfig'] = 'Some ALIS data is missing - please configure the block';
$string['noalis'] = '*No ALIS Data - grade items will be created, but no MTG calculated.';
$string['nostuds'] = 'No Students - ignored';
$string['nogrades'] = 'missing data - MTG not calculated';
$string['nogradescale'] = 'No grade scale was found for $a, and no default scale has been specified.';
$string['distributegrades'] = 'Distribute Target Grades';
$string['noperms'] = 'You don\'t have permission to use this function!';
$string['unsaferegex'] = 'The exclusion pattern you entered in the block\'s settings is unsafe and may overload the server. Matching it will not be attempted until you have edited it. Please see http://www.regular-expressions.info/catastrophic.html for more details.';
$string['uploadalis'] = 'Upload ALIS data';
$string['roles'] = 'Use Roles:';
$string['rolesdesc'] = 'The block will attempt to distribute to users with these roles on each selected course.';

?>