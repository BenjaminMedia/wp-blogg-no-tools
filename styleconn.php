<?php
/**
 * @package styleconn
 * Plugin Name: Styleconn Tools
 * Version: 0.2
 * Description: Some tools to fixing styleconnection.no blogger
 * Author: Niteco
 * Author URI: http://niteco.se/
 * Plugin URI: PLUGIN SITE HERE
 * Text Domain: styleconn
 * Domain Path: /languages
 */

defined('ALLOW_UNFILTERED_UPLOADS') or define('ALLOW_UNFILTERED_UPLOADS', true);

// no limit time
ini_set('max_execution_time', 300);
error_reporting(E_ERROR);

// start up the engine
add_action('admin_menu'             , 'styleconn_menu'     );
add_action('wp_ajax_import_content' , 'styleconn_import_content'    );
add_action('wp_ajax_map_category'   , 'styleconn_map_category'   );
add_action('wp_ajax_fix_image'      , 'styleconn_fix_image'   );

// require helper
require_once 'helper.php';

/**
 * define new menu page parameters
 */
function styleconn_menu() {
    add_menu_page( 'Styleconn Tools', 'Styleconn Tools', 'activate_plugins', 'styleconn', 'styleconn_page', '');
}

/**
 * plugin page
 */
function styleconn_page() {
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    } else { ?>

    <!-- Output for Plugin Options Page -->
    <div class="wrap">
        <?php
            if (isset($_GET['action']) && !empty($_GET['action'])) {
                echo '<div class="updated below-h2" id="message">';
                switch ($_GET['action']) {
                    case 'import_content':
                        echo '<h2>Import Content</h2> <p>Import content from old site to new site !</p>';
                        break;

                    case 'map_category':
                        echo '<h2>Map Category / Post</h2> <p>Map category and post as old site !</p>';
                        break;

                    case 'fix_image':
                        echo '<h2>Fix Image Url</h2> <p>Fixing not corrected image url !</p>';
                        break;

                }
                echo '</div>';
            }
        ?>
        <p>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a href="?page=styleconn&amp;action=import_content" class="nav-link <?php echo (@$_GET['action'] == 'import_content' ? 'active' : ''); ?>">Import content</a>
                </li>
                <li class="nav-item">
                    <a href="?page=styleconn&amp;action=map_category" class="nav-link <?php echo (@$_GET['action'] == 'map_category' ? 'active' : ''); ?>">Map category / post</a>
                </li>
                <li class="nav-item">
                    <a href="?page=styleconn&amp;action=fix_image" class="nav-link <?php echo (@$_GET['action'] == 'fix_image' ? 'active' : ''); ?>">Fix image url</a>
                </li>
                <li class="nav-item">
                    <button id="box-status" class="btn btn-warning" style="display: none;"><span class="fa fa-refresh fa-refresh-animate"></span> Loading...</button>
                </li>
            </ul>
        </p>
    </div>
    <!-- End Output for Plugin Options Page -->

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/css/bootstrap.min.css">
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-beta1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha/js/bootstrap.min.js"></script>
        <style>
            .nav-link {
                cursor: pointer !important;
            }
            .fa-refresh-animate {
                -animation: spin .7s infinite linear;
                -webkit-animation: spin2 .7s infinite linear;
            }
            @-webkit-keyframes spin2 {
                from { -webkit-transform: rotate(0deg);}
                to { -webkit-transform: rotate(360deg);}
            }
            @keyframes spin {
                from { transform: scale(1) rotate(0deg);}
                to { transform: scale(1) rotate(360deg);}
            }
        </style>

    <?php
        if (isset($_GET['action']) && !empty($_GET['action'])) {
            switch ($_GET['action']) {
                case 'import_content':
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('#button-import').click(function () {
                                var data = {
                                    'action': 'import_content',
                                    'urls': jQuery('#input-urls').val(),
                                };
                                jQuery('#box-status').show();
                                jQuery('#result-box').html('');
                                jQuery.ajax({
                                    url: ajaxurl,
                                    cache: false,
                                    data: data,
                                    method: 'POST',
                                    success: function(msg) {
                                        console.log(msg);
                                        jQuery('#box-status').hide();
                                        jQuery('#result-box').html(msg);
                                    },
                                    error: function (msg) {
                                        console.log(msg);
                                        jQuery('#box-status').hide();
                                        jQuery('#result-box').html('Error ...');
                                    }
                                });
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <td>
                                <textarea class="input" placeholder="old styleconnection.no post link" id="input-urls" rows="10" cols="100"></textarea>
                                <br>
                                <button class="btn btn-primary" id="button-import">Start</button>
                            </td>
                        </tr>
                        <tr>
                            <td id="result-box">
                            </td>
                        </tr>
                    </table>
                    <?php
                    break;
                case 'map_category':
                    $helper = new styleconn_helper();
                    $categories = $helper->extract_categories('http://styleconnection.blogg.no/');
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('#button-map').click(function () {
                                var category = jQuery(".category");
                                var index = -1;

                                function doNext() {
                                    if (++index >= category.length) {
                                        jQuery('#box-status').hide();
                                        return;
                                    } else {
                                        jQuery('#box-status').show();
                                    }

                                    var current = category.eq(index);

                                    var name = current.children('.name').first();
                                    var url = current.children('.url').first();
                                    var count = current.children('.count').first();

                                    var data = {
                                        'action': '<?php echo $_GET['action']; ?>',
                                        'name': name.html(),
                                        'url': url.html(),
                                    };

                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        method: 'POST',
                                        success: function(msg) {
                                            console.log(msg);
                                            count.html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            console.log(msg);
                                            count.html('Error ...');
                                        }
                                    });
                                }
                                doNext();
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <th colspan="4">
                                <button class="btn btn-primary" id="button-map">Start</button>
                            </th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>Category</th>
                            <th>Url</th>
                            <th>Result</th>
                        </tr>
                        <?php
                        $i = 0;
                        foreach ($categories as $name => $url) { ?>
                        <tr class="category">
                            <td><?php echo (++$i); ?></td>
                            <td class="name"><?php echo $name; ?></td>
                            <td class="url"><?php echo $url; ?></td>
                            <td class="count"></td>
                        </tr>
                        <?php } ?>
                    </table>
                    <?php
                    break;

                case 'fix_image':
                    $posts = get_posts([
                        'posts_per_page' => 100000,
                        'post_status' => 'any',
                        'orderby' => 'ID',
                        'order'   => 'ASC',
                    ]);
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            var post = jQuery(".post");
                            var index = -1;
                            var current = -1;

                            /**
                             * click stop button
                             */
                            jQuery('#button-stop').click(function () {
                                current = index;
                                index = post.length;
                            });

                            /**
                             * click start button
                             */
                            jQuery('#button-start').click(function () {
                                if (current < post.length) {
                                    index = current;
                                }
                                function doNext() {
                                    if (++index >= post.length) {
                                        jQuery('#box-status').hide();
                                        return;
                                    } else {
                                        current = index;
                                        jQuery('#box-status').show();
                                    }

                                    var current = post.eq(index);
                                    var id = current.attr('attr-id');
                                    var data = {
                                        'action': '<?php echo $_GET['action']; ?>',
                                        'id': id,
                                    };

                                    console.log(id);
                                    jQuery('#result-' + id).html('Loading ...');
                                    jQuery.ajax({
                                        url: ajaxurl,
                                        cache: false,
                                        data: data,
                                        method: 'POST',
                                        success: function(msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html(msg);
                                            doNext();
                                        },
                                        error: function (msg) {
                                            console.log(msg);
                                            jQuery('#result-' + id).html('Error ...');
                                            --index;
                                            setTimeout(doNext, 3000);
                                        }
                                    });
                                }
                                doNext();
                            });
                        });
                    </script>
                    <table class="table table-striped">
                        <tr>
                            <th colspan="4">
                                <button class="btn btn-primary" id="button-start">Start</button>
                                <button class="btn btn-danger" id="button-stop">Stop</button>
                            </th>
                        </tr>
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>Post</th>
                            <th>Result</th>
                        </tr>
                    <?php
                    foreach ($posts as $i => $post) {
                        ?>
                        <tr>
                            <td><?php echo ($i + 1); ?></td>
                            <td><?php echo $post->ID; ?></td>
                            <td class="post" attr-id="<?php echo $post->ID; ?>"><a href="<?php echo get_permalink($post->ID); ?>" target="_blank"><?php echo $post->post_title; ?></a></td>
                            <td id="result-<?php echo $post->ID; ?>"></td>
                        </tr>
                    <?php } ?>
                    </table>
                        <?php
                    break;
            }
        }
    }
}

/**
 * Import content from old site to new site
 */
function styleconn_import_content() {
    $urls = trim($_REQUEST['urls']);
    $urls = explode("\n", $urls);

    $return = array();
    foreach ($urls as $url) {
        $helper = new styleconn_helper();

        // extract / insert content from url to post table
        $return[$url] = $helper->extract_content(trim($url));
    }

    foreach ($return as $url => $result) {
        echo "<a href='{$url}' target='_blank'>{$url}</a>". ' ---> ';
        if (empty($result)) {
            echo '404';
        } else {
            echo "<a href='{$result}' target='_blank'>{$result}</a>";
        }
        echo '<br/>';
    }
    die();
}

/**
 * Map category and post as old site
 */
function styleconn_map_category() {
    $category = trim($_REQUEST['name']);
    $url = trim($_REQUEST['url']);

    $helper = new styleconn_helper();
    $posts = $helper->extract_category($url);
    if (empty($posts)) {
        echo 'error';
    } else {
        $count = 0;
        foreach ($posts as $post) {
            $count += $helper->map_category($post, $category);
        }
        echo 'done - '. $count;
    }
    die();
}

/**
 * Fixing not corrected image url
 */
function styleconn_fix_image() {
    $id = intval($_REQUEST['id']);

    $post = get_post($id);
    $count = (int) substr_count($post->post_content, '<imgsrc');
    if ($count > 0) {
        $post_content = str_replace('<imgsrc', '<img src', $post->post_content);
        wp_update_post([
            'ID' => $post->ID,
            'post_content' => $post_content,
        ]);
        echo 'Fixed <b>' . $count . '</b> image urls';
    } else {
        echo 'Good';
    }
    die();

}