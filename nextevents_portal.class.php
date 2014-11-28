<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2008
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 *
 * $Id$
 */

if ( !defined('EQDKP_INC') ){
	header('HTTP/1.0 404 Not Found');exit;
}

class nextevents_portal extends portal_generic {

	protected static $path		= 'nextevents';
	protected static $data		= array(
		'name'			=> 'Next Events',
		'version'		=> '1.1.0',
		'author'		=> 'WalleniuM',
		'icon'			=> 'fa-calendar-o',
		'contact'		=> EQDKP_PROJECT_URL,
		'description'	=> 'Shows the future events in the portal',
		'lang_prefix'	=> 'nextevents_',
		'multiple'		=> true,
	);
	protected static $positions = array('left1', 'left2', 'right');
	protected static $multiple = true;
	protected static $apiLevel = 20;
	
	public function get_settings($state){
		// build the settings
		$settings	= array(
			'types'	=> array(
				'type'		=> 'dropdown',
				'options'	=> array('raid' => $this->user->lang('calendar_mode_raid'), 'event' => $this->user->lang('calendar_mode_event'), 'all' => $this->user->lang("cl_all")),
			),
			'limit'	=> array(
				'type'		=> 'text',
				'size'		=> '2',
				'default'	=> 5,
			),
			'calendars'	=> array(
				'type'		=> 'multiselect',
				'options'	=> $this->pdh->aget('calendars', 'name', 0, array($this->pdh->get('calendars', 'idlist', array('1')))),
				'size'		=> '2',
			),
			'hideclosed'	=> array(
				'type'		=> 'radio',
			),
			'useflags'	=> array(
				'type'		=> 'radio',
			),
			'showcalendarcolor' => array(
				'type'		=> 'radio',
			),
			'showweekday'	=> array(
				'type'		=> 'radio',
			),
			'showendtime'	=> array(
				'type'		=> 'radio',
			),
		);
		return $settings;
	}
	protected static $install	= array(
		'autoenable'		=> '1',
		'defaultposition'	=> 'right',
		'defaultnumber'		=> '1',
	);

	public function output() {
		// Show all calendars or restrict the output?
		$nr_calendars	= $this->config('calendars');
		$calfilter		= (is_array($nr_calendars) && count($nr_calendars) > 0) ? $nr_calendars : false;

		// Load the event data
		$caleventids	= $this->pdh->sort($this->pdh->get('calendar_events', 'id_list', array(false, $this->time->time, 9999999999, $calfilter)), 'calendar_events', 'date', 'asc');
		
		$raidcal_status = $this->config->get('calendar_raid_status');
		$raidstatus = array();
		if(is_array($raidcal_status)){
			foreach($raidcal_status as $raidcalstat_id){
				if($raidcalstat_id != 4){
					$raidstatus[$raidcalstat_id]	= $this->user->lang(array('raidevent_raid_status', $raidcalstat_id));
				}
			}
		}

		$count_i = 1;
		if(is_array($caleventids) && count($caleventids) > 0){
			$out = '<table width="100%" class="nextraid_table">';
			foreach($caleventids as $eventid){
				$eventextension	= $this->pdh->get('calendar_events', 'extension', array($eventid));
				$raidclosed		= ($this->pdh->get('calendar_events', 'raidstatus', array($eventid)) == '1') ? true : false;
				
				$type = ($this->config('types')) ? $this->config('types') : 'raid';

				if (isset($eventextension['calendarmode']) && $eventextension['calendarmode'] != ""){
					if ($type == 'event') continue;
				} elseif ($type == 'raid') {
					continue;
				}
				
				// calendar dot
				$calendar_icon = '';
				if($this->config('showcalendarcolor')){
					$calendar_id	= $this->pdh->get('calendar_events', 'calendar_id', array($eventid));
					$calendar_color	= $this->pdh->get('calendars', 'color', $calendar_id);
					$calendar_name	= $this->pdh->get('calendars', 'name', $calendar_id);
					if($calendar_color){
						$calendar_icon = '<span style="float:left;width:16px;color:'.$calendar_color.'" class="coretip-right" data-coretip="'.$calendar_name.'"><i class="fa fa-circle"></i></span>';
					}
				}
				
				if($eventextension['calendarmode'] == 'raid') {

					// switch closed raids if enabled
					if($this->config('hideclosed') && $raidclosed){
						continue;
					}
	
					$own_status		= false;
					$count_status	= $count_array = '';
					$raidplink		= $this->routing->build('calendarevent', $this->pdh->get('calendar_events', 'name', array($eventid)), $eventid);
	
					// Build the Attendee Array
					$attendees = array();
					$attendees_raw = $this->pdh->get('calendar_raids_attendees', 'attendees', array($eventid));
					if(is_array($attendees_raw)){
						foreach($attendees_raw as $attendeeid=>$attendeerow){
							if($attendeeid > 0){
								$attendees[$attendeerow['signup_status']][$attendeeid] = $attendeerow;
							}
						}
					}
	
					// Build the guest array
					$guests = '';
					if($this->config->get('calendar_raid_guests') == 1){
						$guestarray = $this->pdh->get('calendar_raids_guests', 'members', array($eventid));
						if(is_array($guestarray)){
							foreach($guestarray as $guest_row){
								$guests[] = $guest_row['name'];
							}
						}
					}
					// get the status counts
					$counts = '';
					foreach($raidstatus as $statusid=>$statusname){
						$counts[$statusid]  = ((isset($attendees[$statusid])) ? count($attendees[$statusid]) : 0);
					}
					$guest_count	= (is_array($guests)) ? count($guests) : 0;
					if(isset($counts[0])){
						$counts[0]		= $counts[0] + $guest_count;
					}

					$signinstatus = $this->pdh->get('calendar_raids_attendees', 'html_status', array($eventid, $this->user->data['user_id']));
					$out .= '<tr class="row1">
								<td colspan="2">
									'.$calendar_icon.'
									<span style="float:left;font-weight:bold;">
										'.$this->time->user_date($this->pdh->get('calendar_events', 'time_start', array($eventid)), false, false, false, true, (($this->config('showweekday') == 1) ? '2' : false)).', '.$this->time->user_date($this->pdh->get('calendar_events', 'time_start', array($eventid)), false, true).(($this->config('showendtime')) ? ' - '.$this->time->user_date($this->pdh->get('calendar_events', 'time_end', array($eventid)), false, true) : '').'
									</span>
									<span style="float: right;width: 24px;">
										'.$signinstatus.'
									</span>
								</td>
							</tr>
							<tr class="row2">
								<td valign="middle" align="center" width="44">
								<a href="'.$raidplink.'">'.$this->pdh->get('event', 'html_icon', array($eventextension['raid_eventid'], 40)).'</a>
								</td>
								<td>';
					if($raidclosed){
						$out .= '<div style="text-decoration: line-through;">'.$this->pdh->get('event', 'name', array($eventextension['raid_eventid'])).' ('.$eventextension['attendee_count'].') </div>';
					}else{
						$out .= '<a href="'.$raidplink.'">'.$this->pdh->get('event', 'name', array($eventextension['raid_eventid'])).' ('.$eventextension['attendee_count'].') </a><br/>';
					}
	
					if(is_array($calfilter) && count($calfilter) > 1){
						$out .= '<span class="calendarname">'.$this->user->lang('calendar').': '.$this->pdh->get('calendars', 'name', array($this->pdh->get('calendar_events', 'calendar_id', array($eventid)))).'</span><br/>';
					}
	
					if (is_array($counts)){
						foreach($counts as $countid=>$countdata){#
							if($this->config('useflags')){
								$out .= '<span class="status'.$countid.' nextevent_statusrow coretip" data-coretip="'.$raidstatus[$countid].'">'.$this->pdh->get('calendar_raids_attendees', 'status_flag', array($countid)).' '.$countdata.'</span>';
							}else{
								$out .= '<span class="status'.$countid.'">'.$raidstatus[$countid].': '.$countdata.'</span><br/>';
							}
						}
					}
					$out .= "</td></tr>";
				
				} else {
					$startendtime	= ($this->pdh->get('calendar_events', 'allday', array($eventid)) > 0) ? '' : ', '.$this->time->user_date($this->pdh->get('calendar_events', 'time_start', array($eventid)), false, true).(($this->config('showendtime')) ? ' - '.$this->time->user_date($this->pdh->get('calendar_events', 'time_end', array($eventid)), false, true) : '');
					$out .= '<tr class="row1">
								<td colspan="2">
									'.$calendar_icon.'
									<span style="font-weight:bold;">
										'.$this->time->user_date($this->pdh->get('calendar_events', 'time_start', array($eventid)), false, false, false, true, (($this->config('showweekday') == 1) ? '2' : false)).$startendtime.'
									</span>
								</td>
							</tr>
							<tr class="row2">
								<td colspan="2">'.$this->pdh->get('calendar_events', 'name', array($eventid)).'
								</td>
							<tr>';
				}

				// end the foreach if x raids are reached
				$tillvalue = ($this->config('limit') > 0) ? $this->config('limit') : 5;
				if($tillvalue <= $count_i){
					break;
				}
				$count_i++;
			}
			$out .= "</table>" ;
		
		}else{
			$out = $this->user->lang('nr_nextevents_noraids');
		}
		return $out;
	}
}
?>