<?php
require_once 'db.php';
require_once 'curl.php';
require_once 'simple_html_dom.php';

$SEARCH_URL = 'http://www.nettruyenmoi.com/tim-truyen?keyword=';

$db = new db('localhost', 'root', '', 'hayso1');
$curl = new cURL();

$mangas = $db->query("select * from so1_manga_mangas")->fetchAll();


if (count($mangas) > 0) {

    foreach ($mangas as $manga) {

        $name = $manga['name'];

        $search = $SEARCH_URL . $name;

        $content = $curl->curlBot($search);

        if ($content) {
            $html = str_get_html($content);

            echo $html; die;

            if ($html->find('figcaption h3 a', 0)) {
                $title = $html->find('figcaption h3 a', 0)->text();

                echo $title; break;
            }
        }
        else {
            $insert = $b->query("insert into so1_fail_jobs (mid, des) values (?,?)", [$manga['id'], 'not found story in nettruyen']);
        }
        break;
    }

}



