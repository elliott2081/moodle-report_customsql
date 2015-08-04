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
 * Custom SQL report.
 *
 * Users with the report/customsql:definequeries capability can enter custom
 * SQL SELECT statements. If they have report/customsql:managecategories
 * capability can create custom categories for the sql reports.
 * Other users with the moodle/site:viewreports capability
 * can see the list of available queries and run them. Reports are displayed as
 * a table. Every data value is a string, and field names come from the database
 * results set.
 *
 * This page shows the list of categorised queries, with edit icons, an add new button
 * if you have the report/customsql:definequeries capability, and a manage categories button
 * ff you have report/customsql:managecategories capability
 *
 * @package report_customsql
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
// DWE customized by David Elliott on 27-04-2015
// Checking the logged user is admin or not
if (is_siteadmin())
{
 $context = context_system::instance();
}
else
{
// Taking context for teachers
global $USER;
$cid = optional_param('cid', 1, PARAM_INT);
$context = get_context_instance(CONTEXT_COURSE,$cid);
}
require_capability('report/customsql:view', $context);

$categories = $DB->get_records('report_customsql_categories', null, 'name ASC');
$showcat = optional_param('showcat', 0, PARAM_INT);
$hidecat = optional_param('hidecat', 0, PARAM_INT);
if (!$showcat && count($categories) == 1) {
    $showcat = reset($categories)->id;
}

// Start the page.
// Edited by DWE on 27-4-2015
// Check the logged user is admin or not
if (is_siteadmin())
admin_externalpage_setup('report_customsql');
else
$PAGE->set_pagelayout('course');


echo $OUTPUT->header();

foreach ($categories as $category) {
    // Are we showing this cat? Default is hidden.
    $show = $category->id == $showcat && $category->id != $hidecat ? 'shown' : 'hidden';

    echo html_writer::start_tag('div', array('class'=>'csql_category csql_category' . $show));
    if ($category->id == $showcat) {
        $params = array('hidecat' => $category->id);
    } else {
        $params = array('showcat' => $category->id);
    }
    $linkhref = new moodle_url('/report/customsql/index.php', $params);
    $link = html_writer::link($linkhref, $category->name, array('class' => 'categoryname'));
    // DWE 0n 27-4-2015
    // Check if the logged user is teacher then check user name also . Dispaly only assigend reports.
    if (is_siteadmin())
{
    $manualreports = $DB->get_records('report_customsql_queries',
            array('runable' => 'manual', 'categoryid' => $category->id), 'displayname');

    $dailyreports = $DB->get_records('report_customsql_queries',
            array('runable' => 'daily', 'categoryid' => $category->id), 'displayname');

    $scheduledreports = $DB->get_records_select('report_customsql_queries',
            "(runable = ? OR runable = ?) AND categoryid = ?",
            array('weekly', 'monthly', $category->id), 'id');
}
else
{
    $manualreports = $DB->get_records('report_customsql_queries',
            array('runable' => 'manual', 'categoryid' => $category->id,'instructors' => $USER->username), 'displayname');

    $dailyreports = $DB->get_records('report_customsql_queries',
            array('runable' => 'daily', 'categoryid' => $category->id,'instructors' => $USER->username), 'displayname');

    $scheduledreports = $DB->get_records_select('report_customsql_queries',
            "(runable = ? OR runable = ?) AND categoryid = ? AND instructors = ?",
            array('weekly', 'monthly', $category->id,$USER->username), 'id');
}


    echo $OUTPUT->heading($link . ' ('.count($manualreports).'/'.count($dailyreports).'/'.count($scheduledreports).')');
    echo html_writer::start_tag('div', array('class'=>'csql_category_reports'));
    if (empty($manualreports) && empty($scheduledreports) && empty($dailyreports)) {
        echo $OUTPUT->heading(get_string('availablereports', 'report_customsql'), 3).
        html_writer::tag('p', get_string('noreportsavailable', 'report_customsql'));
    } else {
        if (!empty($manualreports)) {
            echo $OUTPUT->heading(get_string('availablereports', 'report_customsql'), 3).
            html_writer::tag('p', get_string('manualnote', 'report_customsql'));
            report_customsql_print_reports($manualreports,$cid);
        }
        if (!empty($dailyreports)) {
            echo $OUTPUT->heading(get_string('dailyqueries', 'report_customsql'), 3).
            html_writer::tag('p', get_string('dailynote', 'report_customsql'));
            report_customsql_print_reports($dailyreports,$cid);
        }
        if (!empty($scheduledreports)) {
            echo $OUTPUT->heading(get_string('scheduledqueries', 'report_customsql'), 3).
            html_writer::tag('p', get_string('schedulednote', 'report_customsql'));
            report_customsql_print_reports($scheduledreports,$cid);
        }
    }
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

if (has_capability('report/customsql:definequeries', $context)) {
    echo $OUTPUT->single_button(report_customsql_url('edit.php'),
            get_string('addreport', 'report_customsql'));
}
if (has_capability('report/customsql:managecategories', $context)) {
    echo html_writer::empty_tag('br');
    echo $OUTPUT->single_button(report_customsql_url('manage.php'),
            get_string('managecategories', 'report_customsql'));
}

// Add the reportcategories YUI script to the page.
$PAGE->requires->yui_module('moodle-report_customsql-reportcategories', 'M.report_customsql.init');

echo $OUTPUT->footer();
