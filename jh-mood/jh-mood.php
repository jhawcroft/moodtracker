<?php
/*
Plugin Name: JH Mood Tracker
Plugin URI:  http://www.joshhawcroft.com/wordpress/plugins/mood-tracker/
Description: Widget displays your mood on your blog, and allows you to graphically track your mood over time.
Author: Josh Hawcroft
Version: 1.0
Author URI: http://www.joshhawcroft.com/wordpress/
License: GPLv2 or later
*/
/*  Copyright (c) 2013 Josh Hawcroft <wordpress@joshhawcroft.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class JHWPMoodTrackerChart
{
	private $width = 0;
	private $height = 0;
	private $minY = 0;
	private $maxY = 0;
	private $yAxisLabel = '';
	private $yAxisLegend = array();
	
	private $dataPoints = array();
	
	private $chart = null;


	private function imagelinethick($image, $x1, $y1, $x2, $y2, $color, $thick = 1)
	{
	   if ($thick == 1) {
		   return imageline($image, $x1, $y1, $x2, $y2, $color);
	   }
	   $t = $thick / 2 - 0.5;
	   if ($x1 == $x2 || $y1 == $y2) {
		   return imagefilledrectangle($image, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $color);
	   }
	   $k = ($y2 - $y1) / ($x2 - $x1); //y = kx + q
	   $a = $t / sqrt(1 + pow($k, 2));
	   $points = array(
		   round($x1 - (1+$k)*$a), round($y1 + (1-$k)*$a),
		   round($x1 - (1-$k)*$a), round($y1 - (1+$k)*$a),
		   round($x2 + (1+$k)*$a), round($y2 - (1-$k)*$a),
		   round($x2 + (1-$k)*$a), round($y2 + (1+$k)*$a),
	   );
	   imagefilledpolygon($image, $points, 4, $color);
	   return imagepolygon($image, $points, 4, $color);
	}
	
	
	public function __construct($inWidth = 300, $inHeight = 200)
	{
		$this->width = $inWidth;
		$this->height = $inHeight;
		$this->minY = 1;
		$this->maxY = 5;
		$this->yAxisLegend = array('Terrible', 'A Little Low', 'Content', 'Good', 'Excited');
	}
	
	
	public function set_mood_data($inData = array())
	{
		$this->data_points = $inData;
	}
	
	
	private function make_chart()
	{
		/* create the chart image */
		$this->chart = ImageCreateTrueColor($this->width, $this->height);
		
		imageAlphaBlending($this->chart, false);
		imageSaveAlpha($this->chart, true);
		$trans_color = imageColorAllocateAlpha($this->chart, 255, 255, 255, 127);
		imageFill($this->chart, 0, 0, $trans_color);
		
		/* setup colors */
		$line_color  = ImageColorAllocate($this->chart, 42, 170, 255);
		$axis_color  = ImageColorAllocate($this->chart, 200, 200, 200);
		
		/* draw the axies */
		$this->imagelinethick($this->chart, 1, 1, 1, $this->height, $axis_color, 2); // y
		$this->imagelinethick($this->chart, 1, $this->height-2, 
			$this->width, $this->height-2, $axis_color, 2); // x
		
		/* prepare to plot */
		$point_separation = round($this->width / count($this->data_points));
		$value_separation = round($this->height / ($this->maxY - $this->minY + 1));
		
		/* plot the data */
		$point_index = 0;
		$x = 2;
		$y = 2;
		foreach ($this->data_points as $point_value)
		{
			$y = $this->height - (($point_value - $this->minY) * $value_separation);
			
			if ($point_index != 0)
				$this->imagelinethick($this->chart, $old_x, $old_y, $x, $y, $line_color, 2);

			$old_x = $x;
			$x += $point_separation;
			$old_y = $y;
			$point_index++;
		}
		
	}
	
	
	public function plot_to_file($inFilename)
	{
		$this->make_chart();
		ImagePng($this->chart, $inFilename);
	}
	
	
	public function plot()
	{
		$this->make_chart();
		ImagePng($this->chart);
	}
}



class JHWPWidgetMoodTracker extends WP_Widget
{
    const STRING_ID = 'jh_mood_tracker';

	private static $current_mood = 3;
	private static $mood_update_time = 0;

	private static function table_name()
	{
		global $table_prefix;
		return $table_prefix.self::STRING_ID;
	}
	
	
	private static function install()
	{
		if ( !function_exists('maybe_create_table') ) 
			require_once(ABSPATH . 'wp-admin/install-helper.php');
		$sql = 'CREATE TABLE '.self::table_name().' (
			id int(11) NOT NULL auto_increment,
			mood tinyint(1) NOT NULL default 3,
			date TIMESTAMP NOT NULL,
			INDEX (date),
			PRIMARY KEY (id) )';
		maybe_create_table(self::table_name(), $sql);
	}
	
	
	private static function upgrade()
	{
		//maybe_add_column($this->public, 'date', "ALTER TABLE $this->public ADD date DATE DEFAULT '$date' NOT NULL AFTER active");
	}


	private static function is_installed()
	{
		global $wpdb;
		$table_name = self::table_name();
		if ( $wpdb->get_var($wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )) != $table_name ) 
			return false;
		return true;
	}


	public function __construct()
	{
		// widget?? setup
		parent::__construct(
			self::STRING_ID,
			__('JH Mood Tracker', self::STRING_ID),
			array(
				'description'=>__('Publish and graphically track your mood', self::STRING_ID)
			)
			);
		
		if (!self::is_installed())
			self::install();
		self::get_current_mood();
		//self::check_installed();
	}
	
	
	public function form($instance)
	{
		// admin form options
		if (isset($instance[ 'title' ])) 
			$title = $instance[ 'title' ];
		else 
			$title = __('My Mood', self::STRING_ID);
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?><input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>"></label></p>
		
		<?php 
	}
	
	
	public function update($new_instance, $old_instance)
	{
		// save widget options;
		// return the new instance array to be saved
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}
	
	
	private static function can_log_mood()
	{
		return current_user_can('activate_plugins');
	}
	
	
	private static function get_current_mood()
	{
		global $wpdb;
		$mood = $wpdb->get_row('SELECT mood,date FROM '.self::table_name().
			' ORDER BY id DESC LIMIT 1', ARRAY_N, 0);
		self::$current_mood = $mood[0];
		if ((self::$current_mood < 1) || (self::$current_mood > 5))
			self::$current_mood = 3;
		self::$mood_update_time = strtotime($mood[1]);
	}
	
	
	private static function nbsp($inString)
	{
		return str_replace(' ', '&nbsp;', $inString);
	}
	
	
	private static function current_mood_description()
	{
		switch (self::$current_mood)
		{
		case 1:
			return self::nbsp(__('terrible', self::STRING_ID));
		case 2:
			return self::nbsp(__('a bit low', self::STRING_ID));
		case 4:
			return self::nbsp(__('good', self::STRING_ID));
		case 5:
			return self::nbsp(__('excited', self::STRING_ID));
		case 3:
		default:
			return self::nbsp(__('content', self::STRING_ID));
		}
	}
	
	
	private static function current_mood_image_url()
	{
		return plugins_url('/images/smile_'.self::$current_mood.'.png', __FILE__);
	}
	
	
	private static function nice_date($inFormat, $inTimeOnlyFormat, $inTimestamp)
	{
		$today = date('Y-m-d', time());
		$yesterday = date('Y-m-d', time() - 60 * 60 * 24);
		$comp = date('Y-m-d', $inTimestamp);
		if ($comp == $today)
			return date($inTimeOnlyFormat, $inTimestamp).
				__(' today', self::STRING_ID);
		else if ($comp == $yesterday)
			return date($inTimeOnlyFormat, $inTimestamp).
				__(' yesterday', self::STRING_ID);
		else return date($inFormat, $inTimestamp);
	}
	
	
	private static function get_html_mood()
	{
		$output = '';
		
		$output .= '<img src="'.self::current_mood_image_url().'" border="0" alt=""><br>';
	
		$output .= '<small>@'.self::nice_date('g:i a F j', 'g:i a', self::$mood_update_time).'</small><br>';
	
		$output .= '<em>'.__('I was feeling', self::STRING_ID).' '.
			self::current_mood_description().'.</em>';
			
		$output .= '<br><small><a href="javascript:jh_mood_graph(1);">Chart History</a></small>';
		
		return $output;
	}
	
	
	private static function make_charts()
	{
		global $wpdb;
		
		$mood_data = $wpdb->get_col(
			'SELECT AVG(mood) FROM `'.self::table_name().'` '.
			'WHERE YEAR( DATE ) >= YEAR( NOW( ) ) -1 '.
			'GROUP BY WEEK(date)', 0);
		if ( ($mood_data === false) || (count($mood_data) == 1) )
			$mood_data = $wpdb->get_col(
			'SELECT AVG(mood) FROM `'.self::table_name().'` '.
			'WHERE YEAR( DATE ) >= YEAR( NOW( ) ) -1 '.
			'GROUP BY DAY(date)', 0);
		
		$chart = new JHWPMoodTrackerChart(200, 150);
		$chart->set_mood_data($mood_data);
		$chart->plot_to_file( plugin_dir_path(__FILE__).'/images/chart1.png' );
	}
	
	
	private static function get_html_graph()
	{
		$chart_url = plugins_url('/images/chart1.png', __FILE__);
		$chart_file = plugin_dir_path(__FILE__).'/images/chart1.png';
		
		if (!file_exists($chart_file))
			$output = __('Chart not currently available.', self::STRING_ID);
		else
		{
			$output = '<img src="'.$chart_url.'" border="0" alt="">';
		}
	
		return $output.
			'<br><small><a href="javascript:jh_mood_graph(0);">Current Mood</a></small>';
	}
	
	
	public function widget($args, $instance)
	{
		// actual widget content
		extract($args);
		
		print $before_widget;
		
		$title = apply_filters('widget_title', $instance['title']);
		if (!empty($title))
			print $before_title.$title.$after_title;
		
		print '<p id="jh-mood-current">'.self::get_html_mood().'</p>';
		
		if (self::can_log_mood())
		{
			print '<p><small><em>'.__('I\'m now feeling', self::STRING_ID).'... <a href="javascript:jh_mood_set(5);">'.self::nbsp(__('Excited', self::STRING_ID)).'</a>, <a href="javascript:jh_mood_set(4);">'.self::nbsp(__('Good', self::STRING_ID)).'</a>, <a href="javascript:jh_mood_set(3);">'.self::nbsp(__('Content', self::STRING_ID)).'</a>, <a href="javascript:jh_mood_set(2);">'.self::nbsp(__('A bit low', self::STRING_ID)).'</a>, '.__('or ', self::STRING_ID).'<a href="javascript:jh_mood_set(1);">'.self::nbsp(__('Terrible', self::STRING_ID)).'</a></em></small></p>';
		}		
		
		print $after_widget;
	}
	
	
	public static function ajax_handler()
	{
		global $wpdb;
		
		if (! wp_verify_nonce( $_POST['mood_nonce'], 'jh-mood-nonce' )) exit;
		if (! self::can_log_mood()) exit;
		
		$wpdb->insert(self::table_name(), array(
			'mood'=>intval($_POST['mood']),
			'date'=>current_time('mysql')
			), array('%d', '%s'));
		self::get_current_mood();
		$response = json_encode( self::get_html_mood() ); // can encode objects here
		
		self::make_charts();
 
		header('Content-Type: application/json');
		print $response;
		exit;
	}
	
	
	public static function ajax_graph_handler()
	{
		global $wpdb;
		
		self::get_current_mood();
		
		if (intval($_POST['mood_graph']) == 1) 
			$response = json_encode( self::get_html_graph() );
		else
			$response = json_encode( self::get_html_mood() );
 
		header('Content-Type: application/json');
		print $response;
		exit;
	}
	
	
	public static function ajax_setup()
	{
		wp_enqueue_script('jh-ajax-mood', plugin_dir_url(__FILE__).'js/ajax-mood.js', array('jquery'));
		wp_localize_script('jh-ajax-mood', 'JHAjaxMood', array(
			'ajaxurl'=>admin_url('admin-ajax.php'),
			'mood_nonce'=>wp_create_nonce('jh-mood-nonce')
			));
	}
	
	
	public static function init()
	{
		load_plugin_textdomain(self::STRING_ID, false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages');
		register_widget('JHWPWidgetMoodTracker');
		add_action('wp_enqueue_scripts', array('JHWPWidgetMoodTracker', 'ajax_setup'));
		add_action('wp_ajax_jh-ajax-mood-set', array('JHWPWidgetMoodTracker', 'ajax_handler'));
		add_action('wp_ajax_jh-ajax-mood-graph', array('JHWPWidgetMoodTracker', 'ajax_graph_handler'));
		add_action('wp_ajax_nopriv_jh-ajax-mood-graph', array('JHWPWidgetMoodTracker', 'ajax_graph_handler'));
	}
	
}



add_action('widgets_init', array('JHWPWidgetMoodTracker', 'init'));
	
?>