<?php
/**
 * Plugin Name: Daggerhart Lab - Autogenerate Images
 * Plugin URI: https://github.com/daggerhartlab/daglab-autogen-images
 * GitHub Plugin URI: daggerhartlab/daglab-autogen-images
 * Description: Prevents creation of image derivative sizes until they are needed.
 * Version: 1.0.0
 * Author: Daggerhart Lab
 * Author URI: https://daggerhartlab.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/vendor/autoload.php';

DagLabAutogenImages\Plugin::bootstrap();
