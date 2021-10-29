<?php

function admin_tiled() {
	$maxTiles = 40;
	
	?>
	<p id="p-return"><a href="admin.php">Return to list of courses</a></p>
	<h1>Tiled display</h1>
    <SCRIPT>
        let tiled_max_tiles = <?=$maxTiles?>;
    </SCRIPT>
    <script type="text/javascript" src="static/js/activity.js"></script>
	<SCRIPT>
        let global_activity = []; // Global array contains last activity for each user
        let last_line = 0;
        let frequency = 500; // Update frequency
        let colorChangeSpeed = 100; // Update frequency
        let timenow = 0;
        let activity_filter='<?php
			if (isset($_REQUEST['path']))
				print trim($_REQUEST['path']);
			?>';
        initActive(function(item) {
            global_activity[item['username']] = item;
        }, frequency);
        setInterval(renderTiledResults, frequency);
        setInterval(updateTileColors, colorChangeSpeed);
	
	</SCRIPT>
	<style>
		.tile {
            display:none;
			width: 300px;
			height: 200px;
			margin: 5px
		}
		.tile_code {
            border: 1px solid black;
			margin: 5px;
			height: 170px;
			overflow: scroll;
			background-color: #ffffff;
			font-size: small
		}
		.tile_tooltip {
            visibility: hidden;
            width: 120px;
            background-color: black;
            color: #fff;
            text-align: center;
            padding: 5px 0;
            border-radius: 6px;

            /* Position the tooltip text - see examples below! */
            position: absolute;
            z-index: 1;
		}
	</style>
	<div>
		<?php
		for ($i=0; $i<$maxTiles; $i++) {
			?>
			<style>#tile<?=$i?>:hover #tile<?=$i?>_tooltip { visibility: visible; }</style>
			<div id="tile<?=$i?>" class="tile">
				<div style="margin:0px; padding: 0px">
					<span id="tile<?=$i?>_username" style="float:left; font-size:small"></span>
					<a id="tile<?=$i?>_path" style="float:right; overflow: hidden; width:200px; text-align: right; font-size:small" href="#" target="_blank"></a>
				</div>
				<div style="clear: both;"></div>
                <span id="tile<?=$i?>_tooltip" class="tile_tooltip">
                    <span id="tile<?=$i?>_fullname"></span> (<span id="tile<?=$i?>_time"></span>)
                </span>
				<pre id="tile<?=$i?>_code" class="tile_code">
				</pre>
			</div>
			<?php
		}
		?>
	</div>
	<?php
	admin_log("active users");
}