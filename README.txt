=== Stock Charts for WordPress ===
Contributors: Mte90, iGenius, codeat 
Tags: stock, charts, finance
Requires at least: 3.4
Tested up to: 4.7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

This plugin allow you to display stock values in a fancy chart or in a detailed table.

== Description ==

Put the shortcode you want into a page or a post.

* '[stock-chart]' - For the chart
* '[stock-today]' - For a table with info

**Parameters of the chart shortcode:**

* symbol (default: YHOO) - The stock complete Symbol eg: `[stock-chart symbol="YHOO"]`
* values (default: close) - max, min, close - Value to display on chart, more than one, comma separated, are allowed. eg: `[stock-chart values="close, max"]`
* gap (default: week) - week, month, year, day (required for the next parameter) - Determines time period shown on the chart eg: `[stock-chart gap="day"]`
* days (default: 2) - Numeric value that works combined with gap="day" in order to set the daily gap on the chart eg: `[stock-chart gap="day" days="87"]`
* width (default: 100) - Numeric value that represents the width percentage eg: `[stock-chart width="50"]`
* title (default: none) - Title of the chart. eg: `[stock-chart title="Our stocks high"]`
* legend (default: false) - Display the legend of the chart eg: `[stock-chart legend="true"]`
* round (default: 2) - Set the round for the decimal eg: `[stock-chart round="3"]`

**Parameters of the today shortcode:**

* symbol (default: YHOO) - The stock complete Symbol eg: `[stock-chart symbol="YHOO"]`
* width (default: 100) - Numeric value that represents the width percentage eg: `[stock-chart width="50"]`


To be completed...
== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Place the short-code in your posts or pages

== Frequently asked questions ==



== Screenshots ==



== Changelog ==

= 1.0.0 =

* First public release