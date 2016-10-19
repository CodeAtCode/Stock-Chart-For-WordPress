<?php

/**
 * Plugin Name:       Stock Charts
 * Description:       Display stock charts and quotations in a fancy way. 
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
        'symbol' => 'YHOO',
        'legend' => 'no',
        'values' => 'close',
        'title' => '',
        'round' => '2'
                    ), $atts ) );
    // If you need a daily view start counting gap from yesterday
    if ( $gap === 'day' ) {
        $interval = '-' . $days . ' ' . $gap;
        $end = date( 'Y-m-d', strtotime( '-1 ' . $gap ) );
    } else { // Else start counting from today
        $interval = '-1 ' . $gap;
        $end = date( 'Y-m-d' );
    }
    $initial = date( 'Y-m-d', strtotime( $interval ) );

    // Set transient key as stock-chart + Symbol + Interval
    $key = 'stock-chart-' . $symbol . '_' . $initial . '_' . $end . '_transient';

    // Let's see if we have a cached version
    $json_output = get_transient( $key );
    if ( $json_output === false || empty( $json_output ) ) {
        // If there's no cached version we ask 
        $response = wp_remote_get( "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.historicaldata%20where%20symbol='" . $symbol . "'%20and%20startDate='" . $initial . "'%20and%20endDate='" . $end . "'&format=json&diagnostics=true&env=store://datatables.org/alltableswithkeys", array( 'timeout' => 120 ) );
        if ( is_wp_error( $response ) ) {
            // In case API is down we return the last successful count
            return;
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

    foreach ( $json_output->query->results->quote as $key => $value ) {
        $rclose[] = round( $value->Adj_Close, $round );
        $rmin[] = round( $value->Low, $round );
        $rmax[] = round( $value->High, $round );
        $date[] = 'new Date("' . $value->Date . '")';
    }

    //Reverse arrays in order to display the right sequences of value/date (labels)
    $rclose = array_reverse( $rclose );
    $rmin = array_reverse( $rmin );
    $rmax = array_reverse( $rmax );

    //Push values in a string
    $close = $min = $max = '';
    foreach ( $rclose as $key => $value ) {
        if ( !empty( $value ) ) {
            $close .= '{"value":' . $value . ', "date":' . $date[ $key ] . '},' . "\n";
        }
    }
    foreach ( $rmin as $key => $value ) {
        if ( !empty( $value ) ) {
            $min .= '{"value":' . $value . ', "date":' . $date[ $key ] . '},' . "\n";
        }
    }
    foreach ( $rmax as $key => $value ) {
        if ( !empty( $value ) ) {
            $max .= '{"value":' . $value . ', "date":' . $date[ $key ] . '},' . "\n";
        }
    }

    //Here is where amazing happens

    $varchart = '<div class="stock-chart-container" style="width:' . $width . '%;">' . "\n";
    $varchart .= '<div class="stock-chart-' . str_replace( '.', '', $symbol ) . '"></div>' . "\n";
    if ( $legend !== 'no' ) {
        $varchart .= '<div class="chart-legend">."\n"';
        $varchart .= '</div>' . "\n";
    }
    $varchart .= '</div>' . "\n";

    $varchart .= '<script type="text/javascript">' . "\n";
    $varchart .= 'document.addEventListener("DOMContentLoaded", function(event) {'
            . 'new MG.data_graphic({'
            . 'target: ".stock-chart-' . str_replace( '.', '', $symbol ) . '",
    x_accessor: "date",
    y_accessor: "value",
    title: "' . $title . '",
    missing_is_hidden: true,
    aggregate_rollover: true,
    animate_on_load: true,
    width: 600,
    height: 250,';
    if ( $legend !== 'no' ) {
        $legend_label = '';
        if ( in_array( "min", $stockvalues ) ) {
            $legend_label .= '"min",';
        }
        if ( in_array( "max", $stockvalues ) ) {
            $legend_label .= '"max",';
        }
        if ( in_array( "close", $stockvalues ) ) {
            $legend_label .= '"close"';
        }
        $varchart .= 'legend: [' . $legend_label . '],
    legend_target: ".chart-legend",';
    }
    $varchart .= 'data: [ ' . "\n";
    if ( in_array( "min", $stockvalues ) ) {
        $varchart .= '[' . $min . '],';
    }
    if ( in_array( "max", $stockvalues ) ) {
        $varchart .= '[' . $close . '],';
    }
    if ( in_array( "close", $stockvalues ) ) {
        $varchart .= '[' . $max . ']';
    }
    $varchart .= ']' . "\n"
            . '});' . "\n";
    $varchart .= '})</script>';
    return $varchart;
}

function stock_today( $atts ) {
    extract( shortcode_atts( array(
        'symbol' => 'YHOO',
        'width' => 100,
                    ), $atts ) );

    $key = 'stock-today-' . $symbol . '_transient';

    // Let's see if we have a cached version
    $json_output = get_transient( $key );
    if ( $json_output === false || empty( $json_output ) ) {
        // If there's no cached version we ask 
        $response = wp_remote_get( "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quotes%20where%20symbol%20in%20%28%22" . $symbol . "%22%29&diagnostics=true&env=http%3A%2F%2Fdatatables.org%2Falltables.env&format=json", array( 'timeout' => 120 ) );
        if ( is_wp_error( $response ) ) {
            // In case API is down we return the last successful count
            return;
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
    $varstock = '<div class="stock-today-container stock-today-' . $symbol . '" style="width:' . $width . '%; line-height:1">';
    $varstock .= '<h3 class="today-stock-title">' . __('Stock Today', 'stock-charts') . '</h3>';

    $varstock .= "<span>[" . date( "d-m-Y h:i", strtotime( "-1 hour" ) ) . "]</span>";
    $varstock .= '<table>';

    if ( !empty( $ref->PreviousClose ) ) {
        $varstock .= "<tr><td>" . __('Prev Close Price', 'stock-charts');
        $varstock .= "</td><td>" . $ref->PreviousClose . " €</td></tr>";
    }
    if ( !empty( $ref->Open ) ) {
        $varstock .= "<tr><td>" . __('Open', 'stock-charts');
        $varstock .= "</td><td>" . $ref->Open . " €</td></tr>";
    }
    if ( !empty( $ref->Bid ) ) {
        $varstock .= "<tr><td>" . __('Bid', 'stock-charts');
        $varstock .= "</td><td>" . $ref->Bid . " €</td></tr>";
    }
    if ( !empty( $ref->Ask ) ) {
        $varstock .= "<tr><td>" . __('Ask', 'stock-charts');
        $varstock .= "</td><td>" . $ref->Ask . " €</td></tr>";
    }
    if ( !empty( $ref->OneyrTargetPrice ) ) {
        $varstock .= "<tr><td>" . __('1y Target Est', 'stock-charts');
        $varstock .= "</td><td>" . $ref->OneyrTargetPrice . " €</td></tr>";
    }
    if ( !empty( $ref->DaysRange ) ) {
        $varstock .= "<tr><td>" . __('Day Range', 'stock-charts');
        $varstock .= "</td><td>" . $ref->DaysRange . " €</td></tr>";
    }
    if ( !empty( $ref->YearRange ) ) {
        $varstock .= "<tr><td>" . __('Year Range', 'stock-charts');
        $varstock .= "</td><td>" . $ref->YearRange . " €</td></tr>";
    }
    if ( !empty( $ref->Volume ) ) {
        $varstock .= "<tr><td>" . __('Volume', 'stock-charts');
        $varstock .= "</td><td>" . $ref->Volume . "</td></tr>";
    }
    if ( !empty( $ref->AverageDailyVolume ) ) {
        $varstock .= "<tr><td>" . __('Average Daily Volume', 'stock-charts');
        $varstock .= "</td><td>" . $ref->AverageDailyVolume . "</td></tr>";
    }
    if ( !empty( $ref->MarketCapitalization ) ) {
        $varstock .= "<tr><td>" . __('Market Capitalization', 'stock-charts');
        $varstock .= "</td><td>" . $ref->MarketCapitalization . " €</td></tr>";
    }
    if ( !empty( $ref->DividendYield ) ) {
        $varstock .= "<tr><td>" . __('Dividend Yield', 'stock-charts');
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
            wp_enqueue_script( 'stock-chart-d3', 'https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.0/d3.min.js', array( 'jquery' ), '1.0.0', false );
            wp_enqueue_script( 'stock-chart-script', plugin_dir_url( __FILE__ ) . 'js/metricsgraphics.min.js', array( 'stock-chart-d3' ), '1.0.0', false );
            wp_enqueue_style( 'stock-chart-style', plugin_dir_url( __FILE__ ) . 'css/style.css' );
            // stop the search
            break;
        }
    }

    return $posts;
}

// perform the check when the_posts() function is called
add_action( 'the_posts', 'has_shortcode_stock_chart' );
