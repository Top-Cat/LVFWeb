<?php $title = "Help"; ?>
<div id="hpadding"></div>
<div id="help">
	<h1>London Vehicle Finder help page</h1>
	<h3>Last updated: 01/01/2013</h3>
	<h2>About</h2>
	<p>	The original purpose of LVF was to allow people to find vehicles on the streets of London in real 
		time. Primarily to aid photographers trying to find particular vehicles e.g. all over adverts.
		Everything else that the application does is secondary.</p>
	<p>	I was made aware of the data available in the data feed from TFL Countdown. The first version of the 
		application was a simple screen with a direct access to the feed for data given a registration number&hellip;
		Everything else has been added since then, including a database of over 10,000 vehicles and usage 
		history for most vehicles in London since Sept 2012.</p>
	<p>	LVF is run by a father and son team who run this as a hobby in their spare time and at their expense.
		As this is not a commercial or professional system, please accept that not all issues will be dealt 
		with as promptly as they would be if you paid a subscription to a service that was guaranteed 24x7 
		working. However we endeavour to keep the service working as reliably as we can for most of the time.</p>
	<h2>Usage</h2>
	<p>Data can be requested in a number of ways by :- </p>
	<ul>
		<li>registration</li>
		<li>operator/fleetnumber</li>
		<li>route</li>
		<li>stop</li>
	</ul>

	<p>A history facility is available for all except by stop (see below)</p>
	<p>The entered value will attempt to match in the order:- registration, operator/fleetnumber, route, stop and as a countdown registration.</p>

	<h2>Wildcards</h2>
	<p>In most cases any individual character can be replaced by an underscore '_'  to create a wildcard search. The '*' character can be used to replace multiple characters..</p>

	<h2>Ranges</h2>
	<p>In some cases ranges can be specified. A range is signified by the "-" character. A wildcard cannot be used in a request where a range is specified.</p>
	<p>In all cases of multiple output, a maximum of 100 entries will be displayed. Each of the requests is explained in detail below</p>

	<h2>By Registration:</h2>
	<p>Enter the registration of the vehicle you wish to lookup. Spaces entered are ignored so YX 58 DXA and YX58DXA will be treated the same. Wildcards are allowed as are ranges. Example of valid ranges are YX58DXA-D, returns details of 4 vehicles or GAL E1-100, returns details of 100 vehicles. YX*DXA-D is not valid as it is potentially many ranges.</p>

	<h2>By Route:</h2>
	<p>Any character string less than 5 character long is deemed to be a route number. Wildcards are not allowed as part of the route number. Information will be displayed by direction of travel and then by vehicle fleetnumber order. If a vehicle is not working a journey to the normal destination, the entry will be in blue with the stop name replaced by the actual destination of the vehicle. There are some cases of very common different destinations and in these cases the entry will still be in blue but a code will be displayed to indicate the reason</p>

	<h2>By Stop:</h2>
	<p>Stop ids can be indentified by the 5 character stop codes often displayed in the output from other enquires. If a stop code is entered numerically then spaces and wildcards are not allowed. One or more stop code can be entered by putting a comma between the codes. However no check is made that stop codes are geographically close to each other.</p>
	<p>Alternatively a stop or stops can be entered by name. Wildcards can be used as part of the name. The system will check that what is entered is unique. Because special characters, eg '&amp;', cannot be entered to be able to lookup somewhere like "Elephant &amp; Castle" the "&amp;" must be entered as a wildcard character.</p>
	<p>Vehicles will be displayed in the order they are expected to arrive although this cannot be guaranteed.</p>

	<h2>By Operator/Fleetnumber:</h2>
	<p>Operator codes cannot be wildcarded and must be included in any fleetnumber request. When requesting data on First London vehicles class codes are optional. To lookup more than one vehicle in a request the fleet nummber may include wildcards or a range may be specified</p>

	<h2>Valid Operator codes are :-</h2>
	<table>
		<tr><td>AL</td><td>=</td><td>Arriva London</td><td>FLN</td><td>=</td><td>First London</td><td>ML</td><td>=</td><td>Metroline</td><td></tr>
		<tr><td>ASC</td><td>=</td><td>Arriva Southern Counties</td><td>GAL</td><td>=</td><td>GoAhead London</td><td>SB</td><td>=</td><td>Sullivans Buses</td><td></tr>
		<tr><td>ASE</td><td>=</td><td>Arriva The Shires</td><td>LS</td><td>=</td><td>London Sovereign</td><td>SLN</td><td>=</td><td>Stagecoach London </td><td></tr>
		<tr><td>CTP</td><td>=</td><td>CT Plus</td><td>LU</td><td>=</td><td>London United</td><td>TLN</td><td>=</td><td>Abellio London </td><td></tr>
		<tr><td>EP</td><td>=</td><td>Epsom Buses</td><td>MB</td><td>=</td><td>Metrobus</td></tr>
	</table>

	<h2>History:</h2>
	<p>The facility exists to request the history of a vehicle, ie what routes it has worked on within the last 2 weeks, or the history of a route - what vehicles have been used on the route within the last 2 weeks and when they were last recorded. To obtain history data enter the keyword history followed by a registration, operator/fleetnumber or Route number. This request does not allow wildcards.</p>
</div>