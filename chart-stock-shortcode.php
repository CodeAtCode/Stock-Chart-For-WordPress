<?php

/**
 * Plugin Name:       Stock Chart - from Yahoo Finance APIs
 * Description:       Display stock charts and quotations in a fancy way. Personalize your widget by setting a time interval and colors.
 * Version:           1.0.0
 * Author:            Codeat
 * Author URI:        http://codeat.it
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function stock_chart( $atts ) {
	extract( shortcode_atts( array(
	    'width' => 100,
	    'days' => 2, //useless if gap is not day
	    'gap' => 'week',
	    'layout' => 'light',
	    'symbol' => 'YHOO',
	    'legend' => 'no',
	    'values' => 'close',
	    'title' => '',
	    'round' => '2'
			), $atts ) );
	// If you need a daily view start counting gap from yesterday
	if ( $gap === 'day' ) {
		$interval = '-' . $days . ' ' . $gap;
		$initial = date( 'Y-m-d', strtotime( $interval ) );
		$end = date( 'Y-m-d', strtotime( '-1 ' . $gap ) );
	} else { // Else start counting from today
		$interval = '-1 ' . $gap;
		$initial = date( 'Y-m-d', strtotime( $interval ) );
		$end = date( 'Y-m-d' );
	}

	// Set transient key as stock-chart + Symbol + Interval
	$key = 'stock-chart-' . $symbol . '_' . $initial . '_' . $end . '_transient';

	// Let's see if we have a cached version
	$json_output = get_transient( $key );
	if ( $json_output === false || empty( $json_output ) ) {
		// If there's no cached version we ask 
		$response = wp_remote_get( "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.historicaldata%20where%20symbol='" . $symbol . "'%20and%20startDate='" . $initial . "'%20and%20endDate='" . $end . "'&format=json&diagnostics=true&env=store://datatables.org/alltableswithkeys" );
		if ( is_wp_error( $response ) ) {
			// In case API is down we return the last successful count
			return get_option( $key );
		} else {
			// If everything's okay, parse the body and json_decode it
			$json_output = json_decode( wp_remote_retrieve_body( $response ) );

			// Store the result in a transient, expires after 1 day
			// Also store it as the last successful using update_option
			set_transient( $key, $json_output, HOUR_IN_SECONDS );
			update_option( $key, $json_output );
		}
	}

	// Trim whitespace and explode array of wanted values after comma
	$stockvalues = array_map( 'trim', explode( ',', $values ) );

	//arrays are for reversable purposes
	$rclose = $rmin = $rmax = array();
	$labels = '';

	foreach ( $json_output->query->results->quote as $key => $value ) {
		$rclose[] = round($value->Adj_Close, $round);
		$rmin[] = round($value->Low, $round);
		$rmax[] = round($value->High, $round);
		$labels = '"' . $value->Date . '", ' . $labels;
	}

	//Reverse arrays in order to display the right sequences of value/date (labels)
	$rclose = array_reverse( $rclose );
	$rmin = array_reverse( $rmin );
	$rmax = array_reverse( $rmax );

	//Push values in a string
	$close = $min = $max = '';
	foreach ( $rclose as $value ) {
		if ( !empty( $value ) ) {
			$close .= '"' . $value . '", ';
		}
	}
	foreach ( $rmin as $value ) {
		if ( !empty( $value ) ) {
			$min .= '"' . $value . '", ';
		}
	}
	foreach ( $rmax as $value ) {
		if ( !empty( $value ) ) {
			$max .= '"' . $value . '", ';
		}
	}

	//Here is where amazing happens

	$varchart = '<div class="stock-chart-container" style="width:' . $width . '%;">'."\n";
	if ( !empty( $title ) ) {
		$varchart .= '<h3 class="stock-chart-title">' . $title . '</h3>'."\n";
	}
	$varchart .= '<canvas id="stock-chart-' . $symbol . '"></canvas>'."\n";
	if ( $legend !== 'no' ) {
		$varchart .= '<div class="chart-legend">."\n"'; // add chart legend
		$varchart .= '<ul>'."\n";
		if ( in_array( "min", $stockvalues ) ) {
			$varchart .= '<li class="chart-min">Low</li>."\n"';
		}
		if ( in_array( "max", $stockvalues ) ) {
			$varchart .= '<li class="chart-max">High</li>'."\n";
		}
		if ( in_array( "close", $stockvalues ) ) {
			$varchart .= '<li class="chart-close">Close</li>'."\n";
		}
		$varchart .= '</ul>'."\n";
	}
	$varchart .= '</div>'."\n";

	$varchart .= '<script type="text/javascript">'."\n";
	$varchart .= 'new Chart(document.getElementById("stock-chart-' . $symbol . '").getContext("2d")).Line({
            labels: [' . $labels . '],
            datasets: ['; //choose and add data to chart
	if ( in_array( 'min', $stockvalues ) ) {
		$varchart .= '{
	            	fillColor: "rgba(0,36,46,0.3)",
	            	strokeColor: "rgba(0,36,46,1)",
	            	pointColor: "#00242e",
	            	pointStrokeColor: "#fff",
	            	pointHighlightFill: "rgba(0,36,46,0.6)",
	            	pointHighlightStroke: "#00242e",
	            	data: [' . $min . ']
            	},';
	}
	if ( in_array( 'max', $stockvalues ) ) {
		$varchart .= '{ 
					fillColor: "rgba(0,170,217,0.3)",
					strokeColor: "rgba(0,170,217,1)",
					pointColor: "#00aad9",
					pointStrokeColor: "#fff",
					pointHighlightFill: "rgba(0,170,217,0.6)",
					pointHighlightStroke: "#00aad9",
					data: [' . $max . ']
				},';
	}
	if ( in_array( 'close', $stockvalues ) ) {
		$varchart .= '{
		            fillColor: "rgba(0,104,133,0.3)",
		            strokeColor: "rgba(0,104,133,1)",
		            pointColor: "#006885",
		            pointStrokeColor: "#fff",
		            pointHighlightFill: "rgba(0,104,133,0.6)",
		            pointHighlightStroke: "#006885",
		            data: [' . $close . ']
            	}';
	}
	$varchart .= ']},{';
	if ( $layout === 'dark' ) {
		$varchart .= 'scaleFontColor: "#000", scaleGridLineColor : "rgba(0,0,0,.1)",';
	} else {
		$varchart .= 'scaleFontColor: "#FFF", scaleGridLineColor : "rgba(255,255,255,.1)",';
	}
	$varchart .= 'responsive: true
		  	}
		  	);'."\n";
	$varchart .= '</script>';
	return $varchart;
}

function stock_today( $atts ) {
	extract( shortcode_atts( array(
	    'symbol' => 'YHOO',
	    'width' => 100,
	    'height' => 1,
	    'lang' => 'eng',
			), $atts ) );

	$key = 'stock-today-' . $symbol . '_transient';

	// Let's see if we have a cached version
	$json_output = get_transient( $key );
	if ( $json_output === false || empty( $json_output ) ) {
		// If there's no cached version we ask 
		$response = wp_remote_get( "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quotes%20where%20symbol%20in%20%28%22" . $symbol . "%22%29&diagnostics=true&env=http%3A%2F%2Fdatatables.org%2Falltables.env&format=json" );
		if ( is_wp_error( $response ) ) {
			// In case API is down we return the last successful count
			return get_option( $key );
		} else {
			// If everything's okay, parse the body and json_decode it
			$json_output = json_decode( wp_remote_retrieve_body( $response ) );

			// Store the result in a transient, expires after 1 hour
			// Also store it as the last successful using update_option
			set_transient( $key, $json_output, HOUR_IN_SECONDS );
			update_option( $key, $json_output );
		}
	}

	$ref = $json_output->query->results->quote;
	$varstock = '<div class="stock-today-container stock-today-' . $symbol . '" style="width:' . $width . '%; line-height:' . $height . '">';
	$varstock .= '<h3 class="today-stock-title">';

	//HERE starts rendering a lot of elements according to the language, remove this in multilanguage future release
	
	//For Eugenio
	//What is this shit?!?!!???
	//Port on the boilerplate?
	if ( $lang == 'eng' ) {
		$varstock .= "Stock Today</h3>";
	}
	if ( $lang == 'ita' ) {
		$varstock .= "Il Titolo Oggi</h3>";
	}
	$varstock .= "<span>[" . date( "d-m-Y h:i", strtotime( "-1 hour" ) ) . "]</span>";
	$varstock .= '<table>';

	if ( !empty( $ref->PreviousClose ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Prev Close Price";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Precedente Prezzo Chiusura";
		}
		$varstock .= "</td><td>" . $ref->PreviousClose . " €</td></tr>";
	}
	if ( !empty( $ref->Open ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Open";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Apertura";
		}
		$varstock .= "</td><td>" . $ref->Open . " €</td></tr>";
	}
	if ( !empty( $ref->Bid ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Bid";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Prezzo Acquisto";
		}
		$varstock .= "</td><td>" . $ref->Bid . " €</td></tr>";
	}
	if ( !empty( $ref->Ask ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Ask";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Prezzo Vendita";
		}
		$varstock .= "</td><td>" . $ref->Ask . " €</td></tr>";
	}
	if ( !empty( $ref->OneyrTargetPrice ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "1y Target Est";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Previsione a 1 anno";
		}
		$varstock .= "</td><td>" . $ref->OneyrTargetPrice . " €</td></tr>";
	}
	if ( !empty( $ref->DaysRange ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Day Range";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Variazione Giornaliera";
		}
		$varstock .= "</td><td>" . $ref->DaysRange . " €</td></tr>";
	}
	if ( !empty( $ref->YearRange ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Year Range";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Variazione annuale";
		}
		$varstock .= "</td><td>" . $ref->YearRange . " €</td></tr>";
	}
	if ( !empty( $ref->Volume ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Volume";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Volumi";
		}
		$varstock .= "</td><td>" . $ref->Volume . "</td></tr>";
	}
	if ( !empty( $ref->AverageDailyVolume ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Average Daily Volume";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Volumi Medi Giornalieri";
		}
		$varstock .= "</td><td>" . $ref->AverageDailyVolume . "</td></tr>";
	}
	if ( !empty( $ref->MarketCapitalization ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Market Capitalization";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Capitalizzazione di Mercato";
		}
		$varstock .= "</td><td>" . $ref->MarketCapitalization . " €</td></tr>";
	}
	if ( !empty( $ref->DividendYield ) ) {
		$varstock .= "<tr><td>";
		if ( $lang == 'eng' ) {
			$varstock .= "Dividend Yield";
		}
		if ( $lang == 'ita' ) {
			$varstock .= "Dividendi";
		}
		$varstock .= "</td><td>" . $ref->DividendYield . "%</td></tr>";
	}
	$varstock .= '</table>';
	$varstock .= '</div>';
	return $varstock;
}

//ADD SHORTCODE [stock-chart] [stock-today]
add_shortcode( 'stock-chart', 'stock_chart' );
add_shortcode( 'stock-today', 'stock_today' );

//Load the js and css only when exist
function has_shortcode_stock_chart( $posts ) {
	if ( empty( $posts ) ) {
		return $posts;
	}

	// search through each post
	foreach ( $posts as $post ) {
		// check the post content for the short code
		if ( has_shortcode( $post->post_content, 'stock-chart' ) ) {
			// we have found a post with the short code
			wp_enqueue_script( 'stock-chart-script', plugin_dir_url( __FILE__ ) . 'js/Chart.min.js', array(), '1.0.0', false );
			wp_enqueue_style( 'stock-chart-style', plugin_dir_url( __FILE__ ) . 'css/style.css' );
			// stop the search
			break;
		}
	}

	return $posts;
}

// perform the check when the_posts() function is called
add_action( 'the_posts', 'has_shortcode_stock_chart' );
