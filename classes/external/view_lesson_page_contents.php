<?php

namespace local_botmanager\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use invalid_parameter_exception;
use lesson;
use completion_info;
use context_module;
use stdClass;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir.'/externallib.php');
require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/local/botmanager/lib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir .'/datalib.php');
require_once($CFG->libdir .'/filelib.php');

class view_lesson_page_contents extends \external_api {

    const LOCAL_KASSAI_LESSON_ALL = 2;

    const LOCAL_KASSAI_LESSON_CONTENT_ID_TEXT = 1;
    const LOCAL_KASSAI_LESSON_CONTENT_ID_IMAGE = 2;
    const LOCAL_KASSAI_LESSON_CONTENT_ID_VIDEO = 3;

    const LOCAL_KASSAI_LESSON_CONTENT_TEXT = 'text';
    const LOCAL_KASSAI_LESSON_CONTENT_IMAGE = 'image';
    const LOCAL_KASSAI_LESSON_CONTENT_VIDEO = 'video';

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'pageid' => new external_value(PARAM_INT, 'Page ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'currentpage' => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * View Lesson Page Contents.
     *
     * @param int $pageid
     * @param int $userid
     * @param int $currentpage
     * @return DB object
     * @throws moodle_exception
     */
    public static function execute($pageid, $userid, $currentpage = 0) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $data = array();

        $page = $DB->get_record('lesson_pages', array('id' => $pageid));

        $lesson = $DB->get_record('lesson', array('id'=>$page->lessonid));

        $module = get_coursemodule_from_instance('lesson', $lesson->id, $lesson->course);

        $context = context_module::instance($module->id);


        //print_r($context);


        //print_r($context);

        if ($page) {

            $completed = 0;
            $data['title'] = $page->title;

            $testingObject = get_lesson_pages_contents($page->contents);
            $wholeText = get_lesson_pages_whole_string($testingObject);
            $finalStrings = separateStringWithPunctuation($wholeText);
            $arrayOfContents = get_lesson_content_structures($finalStrings);

            //TODO FALTA AGREGAR OTROS ATRIBUTOS DEL CONTENT
            foreach ($arrayOfContents as $pagePosition=>$page_content) {
                $data['contents'][$pagePosition]['page_content'] = $page_content->content;
                $data['contents'][$pagePosition]['position'] = $pagePosition;

                if ($page_content->type == self::LOCAL_KASSAI_LESSON_CONTENT_TEXT) {
                    $data['contents'][$pagePosition]['type'] = self::LOCAL_KASSAI_LESSON_CONTENT_TEXT;
                    $data['contents'][$pagePosition]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_TEXT;

                } else if ($page_content->type == self::LOCAL_KASSAI_LESSON_CONTENT_VIDEO) {
                    $data['contents'][$pagePosition]['page_content'] =  file_rewrite_pluginfile_urls($page_content->content, 'pluginfile.php', $context->id, 'mod_lesson', 'page_contents', $pageid);
                    $data['contents'][$pagePosition]['type'] = self::LOCAL_KASSAI_LESSON_CONTENT_VIDEO;
                    $data['contents'][$pagePosition]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_VIDEO;
                } else {
                    $data['contents'][$pagePosition]['page_content'] =  file_rewrite_pluginfile_urls($page_content->content, 'pluginfile.php', $context->id, 'mod_lesson', 'page_contents', $pageid);
                    $data['contents'][$pagePosition]['type'] = self::LOCAL_KASSAI_LESSON_CONTENT_IMAGE;
                    $data['contents'][$pagePosition]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_IMAGE;
                }
            }
            /*
            // Get page content.
            $page_contents = $DB->get_records('local_kassai_lesson_content', array(
                'lessonid' => $page->lessonid,
                'pageid' => $pageid,
            ), 'position');

            foreach ($page_contents as $page_content) {
                $data['contents'][$page_content->id]['page_content'] = $page_content->content;
                $data['contents'][$page_content->id]['position'] = $page_content->position;

                if ($page_content->type == self::LOCAL_KASSAI_LESSON_CONTENT_TEXT) {
                    $data['contents'][$page_content->id]['type'] = $page_content->type;
                    $data['contents'][$page_content->id]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_TEXT;
                } else if (local_kassai_is_video($page_content->content)) {
                    $data['contents'][$page_content->id]['type'] = self::LOCAL_KASSAI_LESSON_CONTENT_VIDEO;
                    $data['contents'][$page_content->id]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_VIDEO;
                } else {
                    $data['contents'][$page_content->id]['type'] = self::LOCAL_KASSAI_LESSON_CONTENT_IMAGE;
                    $data['contents'][$page_content->id]['typeid'] = self::LOCAL_KASSAI_LESSON_CONTENT_ID_IMAGE;
                }
            }
                */

            if ($currentpage) {
                // Insert page.
                $lesson_branch = new stdClass();
                $lesson_branch->lessonid = $page->lessonid;
                $lesson_branch->userid = $userid;
                $lesson_branch->pageid = $currentpage;
                $lesson_branch->retry = 0;
                $lesson_branch->flag = 0;
                $lesson_branch->timeseen = time();
                $lesson_branch->nextpageid = $pageid;
                $DB->insert_record('lesson_branch', $lesson_branch);
            }

            if (!$page->nextpageid) { // Lastpage.
                // Insert page.
                $lesson_branch = new stdClass();
                $lesson_branch->lessonid = $page->lessonid;
                $lesson_branch->userid = $userid;
                $lesson_branch->pageid = $pageid;
                $lesson_branch->retry = 0;
                $lesson_branch->flag = 0;
                $lesson_branch->timeseen = time() + 1;
                $lesson_branch->nextpageid = -9;
                $DB->insert_record('lesson_branch', $lesson_branch);

                $completed = 1;
            }


            $data['nextpageid'] = $page->nextpageid;
            $data['currentpage'] = $pageid;

            $firstpage = false;
            if ($pageid == $DB->get_field('lesson_pages',
                    'id',
                    array(
                        'lessonid' => $page->lessonid,
                        'prevpageid' => 0
                    )
                )) {
                $firstpage = true;
            }

            if ($firstpage) {
                // View First page.
                local_botmanager_view_module('lesson', $page->lessonid, $userid);
            }

            if ($lesson_timer = $DB->get_record('lesson_timer',
                array('lessonid' => $page->lessonid,
                    'userid' => $userid,
                    'completed' => 0),
                '*',
                IGNORE_MULTIPLE)) {

                // Update.
                if (!$currentpage) {
                    if ($firstpage) {
                        $lesson_timer->starttime = time();
                    } else {
                        $diff = $lesson_timer->lessontime - $lesson_timer->starttime;
                        $lesson_timer->starttime = time() - $diff;
                    }
                }

                $lesson_timer->lessontime = time();
                $lesson_timer->completed = $completed;

                $DB->update_record('lesson_timer', $lesson_timer);

            } else {
                // Insert.
                $lesson_timer = new stdClass();
                $lesson_timer->lessonid = $page->lessonid;
                $lesson_timer->userid = $userid;
                $lesson_timer->starttime = time();
                $lesson_timer->lessontime = time();
                $lesson_timer->completed = $completed;
                $lesson_timer->timemodifiedoffline = 0;
                $DB->insert_record('lesson_timer', $lesson_timer);
            }

            if ($completed) {
                // Update completion state.
                $cm = get_coursemodule_from_instance('lesson', $page->lessonid, 0, false, MUST_EXIST);
                $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
                $lesson = new lesson($DB->get_record('lesson', array('id' => $cm->instance), '*', MUST_EXIST), $cm, $course);
                $completion = new completion_info($course);
                if ($completion->is_enabled($cm) && $lesson->properties()->completionendreached) {
                    $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
                }
            }

        }

        $transaction->allow_commit();

        return $data;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function execute_returns() {
        return new external_single_structure(
            array(
                'title' => new external_value(PARAM_RAW, 'Standard Moodle primary key.'),
                'contents' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'page_content' => new external_value(PARAM_RAW, 'Standard Moodle primary key.'),
                            'type' => new external_value(PARAM_TEXT, 'Standard Moodle primary key.'),
                            'typeid' => new external_value(PARAM_INT, 'Standard Moodle primary key.'),
                            'position' => new external_value(PARAM_INT, 'Standard Moodle primary key.')
                        )
                    ), VALUE_DEFAULT, ''
                ),
                'nextpageid' => new external_value(PARAM_INT, 'Standard Moodle primary key.'),
                'currentpage' => new external_value(PARAM_INT, 'Standard Moodle primary key.')
            )
        );
    }

}