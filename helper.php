<?php
require_once 'simple_html_dom.php';

class styleconn_helper
{
    public function curl($url)
    {
        $headers[]  = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
        $headers[]  = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        $headers[]  = "Accept-Language:en-us,en;q=0.5";
        $headers[]  = "Accept-Encoding:gzip,deflate";
        $headers[]  = "Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $headers[]  = "Keep-Alive:115";
        $headers[]  = "Connection:keep-alive";
        $headers[]  = "Cache-Control:max-age=0";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_ENCODING, "gzip");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;

    }

    public function file_get_html($url)
    {
        return str_get_html($this->curl($url));
    }

    public function iconv($str)
    {
        return iconv("ISO-8859-1", "UTF-8", trim($str));
    }

    /**
     * get inner text only
     * @param $text
     * @param string $tags
     * @param bool $invert
     * @return mixed
     */
    public function strip_tags_content($text, $tags = '', $invert = FALSE) {

        preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
        $tags = array_unique($tags[1]);

        if(is_array($tags) AND count($tags) > 0) {
            if($invert == FALSE) {
                return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            }
            else {
                return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
            }
        }
        elseif($invert == FALSE) {
            return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
        }
        return $text;
    }

    /**
     * get date time as format
     * @param $str
     * @return string
     */
    public function parseNorwegianDateTime($str){
        $date = '';
        if (preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+) kl\.([0-9]+)\:([0-9]+)/i", $str, $m)){
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
            $hour = $m[4];
            $min = $m[5];
            $date = "$year-$month-$day $hour:$min";
        }
        return $date;
    }

    /**
     * get date time as format
     * @param $str
     * @return bool|string
     */
    public function datetime($str)
    {
        $time = preg_replace("/[^0-9 \-:.]/", "", $str);
        return date('Y-m-d H:i:s', strtotime($time));
    }

    /**
     * extract post data from content page
     * @param $url
     * @return false|string
     */
    public function extract_content($url)
    {
        // Create DOM from URL or file
        $html = file_get_html($url);

        // 404
        if (empty($html)) {
            return '';
        }

        $main = $html->find('#main', 0);
        $entry = $main->find('.entry', 0);
        $comments = $main->find('#comments', 0);

        $data = array();

        // post title
        $post_title = $entry->find('h2', 0);
        $data['post_title'] = $this->iconv($post_title->innertext);

        // post content
        $post_content = $entry->find('.content', 0);
        $data['post_content'] = $this->iconv($post_content->innertext);

        // post time
        $meta = $entry->find('.meta', 0);
        $time = $meta->find('li', 0);
        $category = $time->find('a', 0);
        $data['post_category'] = $this->iconv($category->plaintext);
        $time = $this->strip_tags_content($time->innertext);
        $data['post_time'] = $this->datetime($time);

        // post comment
        $post_comment = array();
        foreach ($comments->find('.comment') as $comment) {
            $array = array();
            $meta = $comment->find('.meta', 0);

            // comment author
            $comment_author = $meta->find('h4', 0);
            $array['author'] = $this->iconv($comment_author->plaintext);
            if (!empty($comment_author->find('a', 0))) {
                // comment author url
                $comment_author_url = $comment_author->find('a', 0);
                $array['author_url'] = $this->iconv($comment_author_url->href);
            } else {
                $array['author_url'] = '';
            }

            // comment time
            $comment_time = $meta->find('p', 0);
            $time = $this->iconv($comment_time->innertext);
            $array['time'] = $this->datetime($time);

            // comment content
            $comment_content = $comment->find('.content', 0);
            $array['content'] = $this->iconv($comment_content->innertext);

            $post_comment[] = $array;
        }
        $data['post_comment'] = $post_comment;

        // then insert data to post table now
        $return = $this->insert_content($data);

        $html->clear();
        return $return;
    }

    /**
     * insert / update post
     * @param $data
     * @return false|string
     */
    public function insert_content($data)
    {
        global $wpdb;

        /**
         * insert post
         */
        $post = $this->get_post($data['post_title'], $data['post_time']);

        if (empty($post)) {
            // insert
            $data_post = array(
                'post_title' => $data['post_title'],
                'post_content' => $data['post_content'],
                'post_date' => $data['post_time'],
                'post_status' => 'publish',
            );
            $post_id = wp_insert_post($data_post);
            $post = get_post($post_id);

            // map category / post
            $category = get_term_by('name', $data['post_category'], 'category');
            wp_set_post_categories($post->ID, $category->term_id);
        } else {
            // update
            $data_post = array(
                'ID' => $post->ID,
                'post_content' => $data['post_content'],
            );
            wp_update_post($data_post);

            // map category / post
            $category = get_term_by('name', $data['post_category'], 'category');
            wp_set_post_categories($post->ID, $category->term_id);

            $comments = get_comments([
                'post_id' => $post->ID,
            ]);

            // remove comment
            $wpdb->delete($wpdb->comments, [
                'comment_post_ID' => $post->ID,
            ]);

            // remove meta comment
            foreach ($comments as $comment) {
                $wpdb->delete($wpdb->commentmeta, [
                    'comment_id' => $comment->comment_ID,
                ]);
            }
        }

        /**
         * insert comment
         */
        foreach ($data['post_comment'] as $comment) {
            $data_comment = array(
                'comment_post_ID' => $post->ID,
                'comment_author' => $comment['author'],
                'comment_author_email' => '',
                'comment_author_url' => $comment['author_url'],
                'comment_content' => $comment['content'],
                'comment_type' => '',
                'comment_parent' => 0,
                'user_id' => 1,
                'comment_author_IP' => '127.0.0.1',
                'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
                'comment_date' => $comment['time'],
                'comment_approved' => 1,
            );
            $wpdb->insert($wpdb->comments, $data_comment);
        }
        wp_update_comment_count($post->ID);

        return get_permalink($post->ID);
    }

    /**
     * extract category list
     * @param $url
     * @return array
     */
    public function extract_categories($url)
    {
        $html = file_get_html($url);
        $side = $html->find('#side', 0);
        $categories = $side->find('> div', 3);
        $data = array();
        foreach ($categories->find('a') as $category) {
            $text = $this->iconv($category->plaintext);
            $url = trim($category->href);
            $data[$text] = $url;
        }

        $html->clear();
        return $data;
    }

    /**
     * extract post list from category page
     * @param $url
     * @return array|bool
     */
    public function extract_category($url)
    {
        $html = file_get_html($url);
        if (empty($html)) {
            return false;
        }

        $main = $html->find('#main', 0);

        $data = array();
        foreach ($main->find('.entry') as $entry) {
            $post = array();

            $title = $entry->find('a', 0);
            $post['post_title'] = $this->iconv($title->plaintext);

            // post time
            $meta = $entry->find('.meta', 0);
            $time = $meta->find('li', 0);
            $time = $this->strip_tags_content($time->innertext);
            $post['post_time'] = $this->datetime($time);

            $data[] = $post;
        }

        $html->clear();
        return $data;
    }

    /**
     * map category and post (search post by title vs time)
     * @param $post_data
     * @param $cat_name
     * @return int
     */
    public function map_category($post_data, $cat_name)
    {
        $post = $this->get_post($post_data['post_title'], $post_data['post_time']);

        if (!empty($post)) {
            $category = get_term_by('name', $cat_name, 'category');
            $result = wp_set_post_categories($post->ID, $category->term_id);
            if (!is_wp_error($result)) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * get post by title vs time
     * @param $post_title
     * @param $post_time
     * @return array|null|object|void
     */
    public function get_post($post_title, $post_time)
    {
        global $wpdb;

        $post = $wpdb->get_row($wpdb->prepare("SELECT ID, post_title, post_date 
        FROM `wp_posts` 
        WHERE post_title = '%s' 
        AND DATE(post_date) = DATE('%s')
        AND post_status", $post_title, $post_time, 'publish'));

        return $post;
    }

}
?>