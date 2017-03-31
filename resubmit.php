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
 * Peer Review resubmission page
 *
 * @package    contrib
 * @subpackage assignment_progress
 * @copyright  2010 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $CFG,$DB,$OUTPUT;
require_once($CFG->dirroot."/mod/peerreview/locallib.php");

// Get course ID and assignment ID
$id     = optional_param('id', 0, PARAM_INT);          // Course module ID
$peerreviewid      = optional_param('peerreviewid', 0, PARAM_INT);           // peerreview ID
$userid = required_param('userid', PARAM_INT);         // User ID

if ($id) {
	$cm = get_coursemodule_from_id('peerreview', $id, 0, false, MUST_EXIST);
	$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
	
} else {
    $peerreview = $DB->get_record('peerreview', array('id'=>$peerreviewid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('peerreview', $peerreview->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}
$context = context_module::instance($cm->id);
if (! $peerreview = $DB->get_record("peerreview", array('id' => $peerreviewid))) {
    print_error('invalidid', 'peerreview');
}
// Check user is logged in and capable of submitting
require_login($course->id, false, $cm);
require_capability('mod/assignment:grade', $context);

// Load up the required assignment code
require('assignment.class.php');
$assignmentclass = 'assignment_peerreview';
$assignmentinstance = new $assignmentclass($cm->id, $peerreview, $cm, $course);

// Get the student info
$student = $DB->get_record('user',array('id'=>$userid));

// Set up the page
$attributes = array('peerreviewid' => $peerreview->id, 'id' => $cm->id);
$PAGE->set_url('/mod/peerreview/resubmit.php', $attributes);
$PAGE->set_title(format_string($peerreview->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Header
/*$navigation = build_navigation($assignmentinstance->strsubmissions, $assignmentinstance->cm);
print_header_simple(format_string($assignmentinstance->assignment->name,true), "", $navigation,
        '', '', true, update_module_button($assignmentinstance->cm->id, $assignmentinstance->course->id, $assignmentinstance->strassignment), navmenu($assignmentinstance->course, $assignmentinstance->cm));
print_heading(get_string('resubmission','assignment_peerreview').": ".fullname($student),1);
*/
if(optional_param('save',NULL,PARAM_TEXT)!=NULL) {

    if (isset($assignmentinstance->assignment->var3) && $assignmentinstance->assignment->var3==assignment_peerreview::ONLINE_TEXT) {
        $submission = $assignmentinstance->get_submission($userid);
        $submission->timemodified = time();
        $submission->data1 = required_param('text',PARAM_CLEANHTML);
        if (update_record('assignment_submissions', $submission)) {
            add_to_log($assignmentinstance->course->id, 'assignment', 'upload', 
                    'view.php?a='.$assignmentinstance->assignment->id, $assignmentinstance->assignment->id, $assignmentinstance->cm->id);
            $OUTPUT->notification(get_string('resubmissionsuccessful','peerreview'),'notifysuccess');
        }
        else {
            $OUTPUT->notification(get_string("uploadnotregistered", "assignment", $newfile_name) );
        }
    }
    else {
        // Process the resubmission
        $dir = $assignmentinstance->file_area_name($userid);
        require_once($CFG->dirroot.'/lib/uploadlib.php');
        $um = new upload_manager('newfile',true,false,$assignmentinstance->course,false,$assignmentinstance->assignment->maxbytes);
        if($um->preprocess_files()) {
        
            //Check the file extension
            $submittedFilename = $um->get_original_filename();
            $extension = $assignmentinstance->assignment->fileextension;
            if(strtolower(substr($submittedFilename,strlen($submittedFilename)-strlen($extension))) != $extension) {
                $OUTPUT->notification(get_string("incorrectfileextension","peerreview",$extension));
            }
            
            // Save the new file and delete the old	
            else if ($um->save_files($dir)) {
                $newfile_name = $um->get_new_filename();
                $um->config->silent = true;
                $um->delete_other_files($dir,$dir.'/'.$newfile_name);
                $submission = $assignmentinstance->get_submission($userid);
                if (set_field('assignment_submissions','timemodified', time(), 'id',$submission->id)) {
                    add_to_log($assignmentinstance->course->id, 'assignment', 'upload', 
                            'view.php?a='.$assignmentinstance->assignment->id, $assignmentinstance->assignment->id, $assignmentinstance->cm->id);
                    $OUTPUT->notification(get_string('resubmissionsuccessful','peerreview'),'notifysuccess');
                }
                else {
                    $OUTPUT->notification(get_string("uploadnotregistered", "assignment", $newfile_name) );
                }
            }
        }
    }
    print_continue($CFG->wwwroot.'/mod/peerreview/submissions.php?id='.$assignmentinstance->cm->id);
}
else {
    if (isset($assignmentinstance->assignment->var3) && $assignmentinstance->assignment->var3==assignment_peerreview::ONLINE_TEXT) {
        $OUTPUT->notification(get_string("resubmissionwarning","peerreview"));
        $mform = new mod_assignment_peerreview_edit_form($CFG->wwwroot.'/mod/assignment/type/peerreview/resubmit.php',array('id'=>$assignmentinstance->cm->id,'a'=>$assignmentinstance->assignment->id,'userid'=>$userid));
        $mform->display();
    }
    else {
    
        // Show form for resubmission
        $OUTPUT->notification(get_string("resubmissionwarning","peerreview"));
        require_once($CFG->libdir.'/filelib.php');
        $icon = mimeinfo('icon', 'xxx.'.$assignmentinstance->assignment->fileextension);
        $type = mimeinfo('type', 'xxx.'.$assignmentinstance->assignment->fileextension);
        $struploadafile = get_string("uploada","peerreview") . "&nbsp;" .
                          "<img align=\"middle\" src=\"".$CFG->pixpath."/f/".$icon."\" class=\"icon\" alt=\"".$icon."\" />" .
                          "<strong>" . $type . "</strong>&nbsp;" .
                          get_string("file","peerreview") . "&nbsp;" .
                          get_string("witha","peerreview") . "&nbsp;<strong>." .
                          $assignmentinstance->assignment->fileextension . "</strong>&nbsp;" .
                          get_string("extension","peerreview");
        $strmaxsize = get_string("maxsize", "", display_size($assignmentinstance->assignment->maxbytes));

        echo '<div style="text-align:center">';
        echo '<form enctype="multipart/form-data" method="post" '.
             "action=\"$CFG->wwwroot/mod/assignment/type/peerreview/resubmit.php\">";
        echo '<fieldset class="invisiblefieldset">';
        echo "<p>$struploadafile ($strmaxsize)</p>";
        echo '<input type="hidden" name="id" value="'.$assignmentinstance->cm->id.'" />';
        echo '<input type="hidden" name="a" value="'.$assignmentinstance->assignment->id.'" />';
        echo '<input type="hidden" name="userid" value="'.$userid.'" />';
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        require_once($CFG->libdir.'/uploadlib.php');
        upload_print_form_fragment(1,array('newfile'),false,null,0,$assignmentinstance->assignment->maxbytes,false);
        echo '<input type="submit" name="save" value="'.get_string('uploadthisfile').'" />';
        echo '</fieldset>';
        echo '</form>';
        echo '</div>';
    }
}
