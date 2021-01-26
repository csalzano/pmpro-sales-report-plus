<?php
defined( 'ABSPATH' ) or exit;

/**
 * Plugin Name: Paid Memberships Pro - Sales Report Plus
 * Plugin URI: https://github.com/csalzano/pmpro-sales-report-plus
 * Description: Adds a table full of customer names and dollar amounts to a copy of the Sales & Revenue report.
 * Author: Corey Salzano
 * Version: 0.1.0
 * License: GPLv2 or later
 */


/*
	PMPro Report
	Title: Sales Plus
	Slug: sales-plus

	For each report, add a line like:
	global $pmpro_reports;
	$pmpro_reports['slug'] = 'Title';

	For each report, also write two functions:
	* pmpro_report_{slug}_widget()   to show up on the report homepage.
	* pmpro_report_{slug}_page()     to show up when users click on the report page widget.
*/
global $pmpro_reports;
$gateway_environment = pmpro_getOption("gateway_environment");
if($gateway_environment == "sandbox")
	$pmpro_reports['sales_plus'] = __('Sales and Revenue Plus (Testing/Sandbox)', 'paid-memberships-pro' );
else
	$pmpro_reports['sales_plus'] = __('Sales and Revenue Plus', 'paid-memberships-pro' );

//queue Google Visualization JS on report page
function pmpro_report_sales_plus_init()
{
	if ( is_admin() && isset( $_REQUEST['report'] ) && $_REQUEST[ 'report' ] == 'sales_plus' && isset( $_REQUEST['page'] ) && $_REQUEST[ 'page' ] == 'pmpro-reports' ) {
		wp_enqueue_script( 'corechart', plugins_url( 'js/corechart.js', PMPRO_BASE_FILE ) );
	}

}
add_action("init", "pmpro_report_sales_plus_init");

//widget
function pmpro_report_sales_plus_widget() {
	global $wpdb;
?>
<style>
	#pmpro_report_sales tbody td:last-child {text-align: right; }
</style>
<span id="pmpro_report_sales" class="pmpro_report-holder">
	<table class="wp-list-table widefat fixed striped">
	<thead>
		<tr>
			<th scope="col">&nbsp;</th>
			<th scope="col"><?php _e('Sales', 'paid-memberships-pro' ); ?></th>
			<th scope="col"><?php _e('Revenue', 'paid-memberships-pro' ); ?></th>
		</tr>
	</thead>
	<?php
		$reports = array(
			'today'      => __('Today', 'paid-memberships-pro' ),
			'this month' => __('This Month', 'paid-memberships-pro' ),
			'this year'  => __('This Year', 'paid-memberships-pro' ),
			'all time'   => __('All Time', 'paid-memberships-pro' ),
		);

	foreach ( $reports as $report_type => $report_name ) {
		//sale prices stats
		$count = 0;
		$max_prices_count = apply_filters( 'pmpro_admin_reports_max_sale_prices', 5 );
		$prices = pmpro_get_prices_paid( $report_type, $max_prices_count );	
		?>
		<tbody>
			<tr class="pmpro_report_tr">
				<th scope="row">
					<?php if( ! empty( $prices ) ) { ?>
						<button class="pmpro_report_th pmpro_report_th_closed"><?php echo esc_html($report_name); ?></button>
					<?php } else { ?>
						<?php echo esc_html($report_name); ?>
					<?php } ?>
				</th>
				<td><?php echo esc_html( number_format_i18n( pmpro_getSales( $report_type ) ) ); ?></td>
				<td><?php echo esc_html(pmpro_formatPrice( pmpro_getRevenue( $report_type ) ) ); ?></td>
			</tr>
			<?php
				//sale prices stats
				$count = 0;
				$max_prices_count = apply_filters( 'pmpro_admin_reports_max_sale_prices', 5 );
				$prices = pmpro_get_prices_paid( $report_type, $max_prices_count );
				foreach ( $prices as $price => $quantity ) {
					if ( $count++ >= $max_prices_count ) {
						break;
					}
			?>
				<tr class="pmpro_report_tr_sub" style="display: none;">
					<th scope="row">- <?php echo esc_html( pmpro_formatPrice( $price ) );?></th>
					<td><?php echo esc_html( number_format_i18n( $quantity ) ); ?></td>
					<td><?php echo esc_html( pmpro_formatPrice( $price * $quantity ) ); ?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
		<?php
	}
	?>
	</table>
	<?php if ( function_exists( 'pmpro_report_sales_page' ) ) { ?>
		<p class="pmpro_report-button">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-reports&report=sales_plus' ) ); ?>"><?php _e('Details', 'paid-memberships-pro' );?></a>
		</p>
	<?php } ?>
</span>

<?php
}

function pmpro_report_sales_plus_page()
{
	global $wpdb, $pmpro_currency_symbol, $pmpro_currency, $pmpro_currencies;

	//get values from form
	if(isset($_REQUEST['type']))
		$type = sanitize_text_field($_REQUEST['type']);
	else
		$type = "revenue";

	if($type == "sales")
		$type_function = "COUNT";
	else
		$type_function = "SUM";

	if(isset($_REQUEST['period']))
		$period = sanitize_text_field($_REQUEST['period']);
	else
		$period = "daily";

	if(isset($_REQUEST['month']))
		$month = intval($_REQUEST['month']);
	else
		$month = date_i18n("n", current_time('timestamp'));

	$thisyear = date_i18n("Y", current_time('timestamp'));
	if(isset($_REQUEST['year']))
		$year = intval($_REQUEST['year']);
	else
		$year = $thisyear;

	if(isset($_REQUEST['level']))
		$l = intval($_REQUEST['level']);
	else
		$l = "";

	if ( isset( $_REQUEST[ 'discount_code' ] ) ) {
		$discount_code = intval( $_REQUEST[ 'discount_code' ] );
	} else {
		$discount_code = '';
	}

	$currently_in_period = false;

	//calculate start date and how to group dates returned from DB
	if($period == "daily")
	{
		$startdate = $year . '-' . substr("0" . $month, strlen($month) - 1, 2) . '-01';
		$enddate = $year . '-' . substr("0" . $month, strlen($month) - 1, 2) . '-31';
		$date_function = 'DAY';
		$currently_in_period = ( intval( date( 'Y' ) ) == $year && intval( date( 'n' ) ) == $month );
	}
	elseif($period == "monthly")
	{
		$startdate = $year . '-01-01';
		$enddate = strval(intval($year)+1) . '-01-01';
		$date_function = 'MONTH';
		$currently_in_period = ( intval( date( 'Y' ) ) == $year );
	}
	else
	{
		$startdate = '1970-01-01';	//all time
		$date_function = 'YEAR';
		$currently_in_period = true;
	}

	//testing or live data
	$gateway_environment = pmpro_getOption("gateway_environment");

	//get data
	$query = array();
	
	$query['select'] = "SELECT $date_function(o.timestamp) as date, $type_function(o.total) as value ";
	$query['from'] = "FROM $wpdb->pmpro_membership_orders o ";

	if ( ! empty( $discount_code ) ) {
		$query['from'] .= "LEFT JOIN $wpdb->pmpro_discount_codes_uses dc ON o.id = dc.order_id ";
	}

	$query['where'] = "WHERE o.total > 0 AND o.timestamp >= '" . esc_sql( $startdate ) . "' AND o.status NOT IN('refunded', 'review', 'token', 'error') AND o.gateway_environment = '" . esc_sql( $gateway_environment ) . "' ";

	if(!empty($enddate))
		$query['where'] .= "AND o.timestamp <= '" . esc_sql( $enddate ) . "' ";

	if(!empty($l))
		$query['where'] .= "AND o.membership_id IN(" . esc_sql( $l ) . ") ";

	if ( ! empty( $discount_code ) ) {
		$query['where'] .= "AND dc.code_id = '" . esc_sql( $discount_code ) . "' ";
	}

	$query['group_by'] = " GROUP BY date ";
	$query['order_by'] = "ORDER BY date ";

	$sqlQuery = implode( ' ', $query );

	$dates = $wpdb->get_results($sqlQuery);

	//fill in blanks in dates
	$cols = array();
	$total_in_period = 0;
	$units_in_period = 0; // Used for averages.
	
	if($period == "daily")
	{
		$lastday = date_i18n("t", strtotime($startdate, current_time("timestamp")));
		$day_of_month = intval( date( 'j' ) );
		
		for($i = 1; $i <= $lastday; $i++)
		{
			$cols[$i] = 0;
			if ( ! $currently_in_period || $i < $day_of_month ) {
				$units_in_period++;
			}
			
			foreach($dates as $date)
			{
				if($date->date == $i) {
					$cols[$i] = $date->value;
					if ( ! $currently_in_period || $i < $day_of_month ) {
						$total_in_period += $date->value;
					}
				}	
			}
		}
	}
	elseif($period == "monthly")
	{
		$month_of_year = intval( date( 'n' ) );
		for($i = 1; $i < 13; $i++)
		{
			$cols[$i] = 0;
			if ( ! $currently_in_period || $i < $month_of_year ) {
				$units_in_period++;
			}

			foreach($dates as $date)
			{
				if($date->date == $i) {
					$cols[$i] = $date->value;
					if ( ! $currently_in_period || $i < $month_of_year ) {
						$total_in_period += $date->value;
					}
				}
			}
		}
	}
	else //annual
	{
		//get min and max years
		$min = 9999;
		$max = 0;
		foreach($dates as $date)
		{
			$min = min($min, $date->date);
			$max = max($max, $date->date);
		}

		$current_year = intval( date( 'Y' ) );
		for($i = $min; $i <= $max; $i++)
		{
			if ( $i < $current_year ) {
				$units_in_period++;
			}
			foreach($dates as $date)
			{
				if($date->date == $i) {
					$cols[$i] = $date->value;
					if ( $i < $current_year ) {
						$total_in_period += $date->value;
					}
				}
			}
		}
	}
	
	$average = 0;
	if ( 0 !== $units_in_period ) {
		$average = $total_in_period / $units_in_period; // Not including this unit.
	}
	?>
	<form id="posts-filter" method="get" action="">
	<h1>
		<?php _e('Sales and Revenue Plus', 'paid-memberships-pro' );?>
	</h1>

	<div class="tablenav top">
		<?php _e('Show', 'paid-memberships-pro' )?>
		<select id="period" name="period">
			<option value="daily" <?php selected($period, "daily");?>><?php _e('Daily', 'paid-memberships-pro' );?></option>
			<option value="monthly" <?php selected($period, "monthly");?>><?php _e('Monthly', 'paid-memberships-pro' );?></option>
			<option value="annual" <?php selected($period, "annual");?>><?php _e('Annual', 'paid-memberships-pro' );?></option>
		</select>
		<select name="type">
			<option value="revenue" <?php selected($type, "revenue");?>><?php _e('Revenue', 'paid-memberships-pro' );?></option>
			<option value="sales" <?php selected($type, "sales");?>><?php _e('Sales', 'paid-memberships-pro' );?></option>
		</select>
		<span id="for"><?php _e('for', 'paid-memberships-pro' )?></span>
		<select id="month" name="month">
			<?php for($i = 1; $i < 13; $i++) { ?>
				<option value="<?php echo esc_attr( $i );?>" <?php selected($month, $i);?>><?php echo esc_html(date_i18n("F", mktime(0, 0, 0, $i, 2)));?></option>
			<?php } ?>
		</select>
		<select id="year" name="year">
			<?php for($i = $thisyear; $i > 2007; $i--) { ?>
				<option value="<?php echo esc_attr( $i );?>" <?php selected($year, $i);?>><?php echo esc_html( $i );?></option>
			<?php } ?>
		</select>
		<span id="for"><?php _e('for', 'paid-memberships-pro' )?></span>
		<select id="level" name="level">
			<option value="" <?php if(!$l) { ?>selected="selected"<?php } ?>><?php _e('All Levels', 'paid-memberships-pro' );?></option>
			<?php
				$levels = $wpdb->get_results("SELECT id, name FROM $wpdb->pmpro_membership_levels ORDER BY name");
				foreach($levels as $level)
				{
			?>
				<option value="<?php echo esc_attr( $level->id ); ?>" <?php if($l == $level->id) { ?>selected="selected"<?php } ?>><?php echo esc_html( $level->name); ?></option>
			<?php
				}
			?>
		</select>
		<?php
		$sqlQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM $wpdb->pmpro_discount_codes ";
		$sqlQuery .= "ORDER BY id DESC ";
		$codes = $wpdb->get_results($sqlQuery, OBJECT);
		if ( ! empty( $codes ) ) { ?>
		<select id="discount_code" name="discount_code">
			<option value="" <?php if ( empty( $discount_code ) ) { ?>selected="selected"<?php } ?>><?php _e('All Codes', 'paid-memberships-pro' );?></option>
			<?php foreach ( $codes as $code ) { ?>
				<option value="<?php echo esc_attr( $code->id ); ?>" <?php selected( $discount_code, $code->id ); ?>><?php echo esc_html( $code->code ); ?></option>
			<?php } ?>
		</select>
		<?php } ?>
		<input type="hidden" name="page" value="pmpro-reports" />
		<input type="hidden" name="report" value="sales_plus" />
		<input type="submit" class="button action" value="<?php _e('Generate Report', 'paid-memberships-pro' );?>" />
	</div>
	<div id="chart_div" style="clear: both; width: 100%; height: 500px;"></div>
	<p>* <?php _e( 'Average line calculated using data prior to current day, month, or year.', 'paid-memberships-pro' ); ?></p>
	<script>
		//update month/year when period dropdown is changed
		jQuery(document).ready(function() {
			jQuery('#period').change(function() {
				pmpro_ShowMonthOrYear();
			});
		});

		function pmpro_ShowMonthOrYear()
		{
			var period = jQuery('#period').val();
			if(period == 'daily')
			{
				jQuery('#for').show();
				jQuery('#month').show();
				jQuery('#year').show();
			}
			else if(period == 'monthly')
			{
				jQuery('#for').show();
				jQuery('#month').hide();
				jQuery('#year').show();
			}
			else
			{
				jQuery('#for').hide();
				jQuery('#month').hide();
				jQuery('#year').hide();
			}
		}

		pmpro_ShowMonthOrYear();

		//draw the chart
		google.charts.load('current', {'packages':['corechart']});
		google.charts.setOnLoadCallback(drawVisualization);
		function drawVisualization() {

			var data = google.visualization.arrayToDataTable([
				[
					{ label: '<?php echo esc_html( $date_function );?>' },
					{ label: '<?php echo esc_html( ucwords( $type ) );?>' },
					{ label: '<?php _e( 'Average*', 'paid-memberships-pro' );?>' },
				],
				<?php foreach($cols as $date => $value) { ?>
					['<?php
						if($period == "monthly") {
							echo esc_html(date_i18n("M", mktime(0,0,0,$date,2)));
						} else {
						echo esc_html( $date );
					} ?>', <?php echo esc_html( pmpro_round_price( $value ) );?>, <?php echo esc_html( pmpro_round_price( $average ) );?>],
				<?php } ?>
			]);

			var options = {
				colors: ['<?php
					if ( $type === 'sales') {
						echo '#0099c6'; // Blue for "Sales" chart.
					} else {
						echo '#51a351'; // Green for "Revenue" chart.
					}
				?>'],
				chartArea: {width: '90%'},
				hAxis: {
					title: '<?php echo esc_html( $date_function );?>',
					textStyle: {color: '#555555', fontSize: '12', italic: false},
					titleTextStyle: {color: '#555555', fontSize: '20', bold: true, italic: false},
					maxAlternation: 1
				},
				vAxis: {
					<?php if ( $type === 'sales') { ?>
						format: '0',
					<?php } ?>
					textStyle: {color: '#555555', fontSize: '12', italic: false},
				},
				seriesType: 'bars',
				series: {1: {type: 'line', color: 'red'}},
				legend: {position: 'none'},
			};

			<?php
				if($type != "sales")
				{	
					$decimals = isset( $pmpro_currencies[ $pmpro_currency ]['decimals'] ) ? (int) $pmpro_currencies[ $pmpro_currency ]['decimals'] : 2;
					
					$decimal_separator = isset( $pmpro_currencies[ $pmpro_currency ]['decimal_separator'] ) ? $pmpro_currencies[ $pmpro_currency ]['decimal_separator'] : '.';
					
					$thousands_separator = isset( $pmpro_currencies[ $pmpro_currency ]['thousands_separator'] ) ? $pmpro_currencies[ $pmpro_currency ]['thousands_separator'] : ',';
					
					if ( pmpro_getCurrencyPosition() == 'right' ) {
						$position = "suffix";
					} else {
						$position = "prefix";
					}
					?>
					var formatter = new google.visualization.NumberFormat({
						<?php echo esc_html( $position );?>: '<?php echo esc_html( html_entity_decode($pmpro_currency_symbol) ); ?>',
						'decimalSymbol': '<?php echo esc_html( html_entity_decode( $decimal_separator ) ); ?>',
						'fractionDigits': <?php echo intval( $decimals ); ?>,
						'groupingSymbol': '<?php echo esc_html( html_entity_decode( $thousands_separator ) ); ?>',
					});
					formatter.format(data, 1);
					formatter.format(data, 2);
					<?php
				}
			?>

			var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
			chart.draw(data, options);
		}
	</script>

	</form>
	<?php



	//OK you need to make this plugin a repo and repeat this change:

	/**
	 * @param  integer $units_in_period Used for averages.
	 */
	$report_args = array(
		'dates'               => $dates,
		'cols'                => $cols,
		'total_in_period'     => $total_in_period,
		'units_in_period'     => $units_in_period,
		'type'                => $type, //"revenue" or "sales"
		'type_function'       => $type_function, //"COUNT" or "SUM"
		'period'              => $period, //"daily" or "monthly"
		'month'               => $month, //integer month no leading zero, 3 = march
		'year'                => $year, //4 digit year from date()
		'l'                   => $l, //string, default empty
		'discount_code'       => $discount_code, //integer or empty string
		'startdate'           => $startdate, // string yyyy-mm-dd
		'currently_in_period' => $currently_in_period, //boolean
		'query'               => $query, //array of SQL query pieces 
	);
	if( isset( $enddate ) )
	{
		$report_args['enddate'] = $enddate; // string yyyy-mm-dd		
	}
	do_action( 'pmpro_after_report_sales', $report_args );
}

function add_members_table_below_chart(	$args )
{
	if( empty( $args['query'] ) || ! is_array( $args['query'] ) )
	{
		return;
	}

	//Build a new query using the FROM and WHERE from $args['query']
	
	$q = sprintf( 
		'SELECT timestamp, user_id, billing_name, total %s %s ORDER BY timestamp DESC',
		$args['query']['from'],
		$args['query']['where']
	);

	global $wpdb, $pmpro_currency_symbol;
	$rows = $wpdb->get_results( $wpdb->prepare( $q ) );

	echo '<table class="widefat striped">'
		. '<thead><tr>'
		. '<th>' . __( 'Date', 'paid-memberships-pro' ) . '</th>'
		. '<th>' . __( 'Member', 'paid-memberships-pro' ) . '</th>'
		. '<th>' . __( 'Total', 'paid-memberships-pro' ) . '</th>'
		. '</tr></thead>';

	/*
	date:"1970"
	value:"550"
	user_id:"18"
	billing_name:"Cammy Moring"
	*/
	foreach( $rows as $row )
	{
		$total = '';
		if( 'left' == pmpro_getCurrencyPosition() )
		{
			$total .= $pmpro_currency_symbol;
		}
		$total .= number_format( $row->total, 2, '.', ',' );
		if( 'right' == pmpro_getCurrencyPosition() )
		{
			$total .= $pmpro_currency_symbol;
		}

		printf(
			'<tr><td>%s</td><td><a href="%s">%s</a></td><td>%s</td></tr>',
			date( 'F j, Y', strtotime( $row->timestamp ) ),
			get_edit_user_link( $row->user_id ),
			get_user_by( 'id', $row->user_id )->display_name,
			$total
		);
	}

	echo '</table>';
}
add_action( 'pmpro_after_report_sales', 'add_members_table_below_chart', 10, 1 );
