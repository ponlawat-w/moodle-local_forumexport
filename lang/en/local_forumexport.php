<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings
 * 
 * @package local_forumexport
 * @copyright 2022 Ponlawat Weerapanpisit
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Forum Export (extended functionalities)';

$string['forumexport:exportforum'] = 'Export forum data';
$string['forumexport:exportdifferentgroup'] = 'Export data from different groups';
$string['forumexport:includereplies'] = 'Include replies from different groups when export';
$string['forumexport:includeparent'] = 'Include parent post from different groups when export';

$string['export_extendedfunctionalities'] = 'Export (extended functionalities)';

$string['groupoptions'] = 'Group options';
$string['groupmode'] = 'Export discussions started from';
$string['groupmode_all'] = 'All groups';
$string['groupmode_my'] = 'My groups';
$string['groupmode_custom'] = 'Custom groups';
$string['groups'] = 'Groups to export';
$string['groupbydiscussiongroup'] = 'Export posts in the discussions of the selected groups';
$string['groupbydiscussionstarter'] = 'Export posts in the discussions started by users of the selected groups';
$string['groupbyparticipants'] = 'Export posts participated by users of the selected groups';
$string['includeparent'] = 'Include parent discussion post when created by a different group';
$string['includeallreplies'] = 'Include replies from members in different groups';

$string['engagement_method'] = 'Engagement Method';
$string['engagement_method_help'] = '<p>Engagement Calculation Method</p><strong>Person-to-Person Engagement:</strong> The engagement level increases each time a user replies to the same user in the same thread.<br><strong>Thread Total Count Engagement:</strong> The engagement level increases each time a user participate in the same thread.<br><strong>Thread Engagement:</strong> The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';
$string['engagement_persontoperson'] = 'Person-to-Person Engagement';
$string['engagement_persontoperson_description'] = 'The engagement level increases each time a user replies to the same user in the same thread.';
$string['engagement_threadtotalcount'] = 'Thread Total Count Engagement';
$string['engagement_threadtotalcount_description'] = 'The engagement level increases each time a user participate in the same thread.';
$string['engagement_threadengagement'] = 'Thread Engagement';
$string['engagement_threadengagement_description'] = 'The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';

$string['engagement_admin_defaultmethod'] = 'Default Engagement Calculation Method';
