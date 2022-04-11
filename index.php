<?php
require_once 'db.php';
require_once 'curl.php';
require_once 'simple_html_dom.php';

set_time_limit(0);

$SEARCH_URL = 'http://www.nettruyenmoi.com/tim-truyen?keyword=';

$db = new db('localhost', 'root', '', 'hayso1');
$curl = new cURL();

$mangas = $db->query("select * from so1_manga_mangas where craw is NULL")->fetchAll();


if (count($mangas) > 0) {

    $data = [];

    $total = count($mangas);

    for ($i = 0; $i < count($mangas); $i++) {

        $manga = $mangas[$i];

        $name = $manga['name'];
        $mangaId =  $manga['id'];

        $searchString = implode('+', explode(' ', $name));

        $search = $SEARCH_URL . $searchString;

        $content = $curl->getContent($search, ['Host: www.nettruyenmoi.com'], 1);


        if ($content) {
            $html = str_get_html($content);

            if ($html->find('figcaption h3 a')) {

                $titles = $html->find('figcaption h3 a');

                $linkStory = null;

                foreach($titles as $title) {
                    $titleText = $title->text();

                    if (strpos(strtolower($titleText), strtolower($name)) !== false) {
                        $linkStory = $title->getAttribute('href');
                        break;
                    }
                }
                
                if ($linkStory) {

                    $content = $curl->getContent($linkStory, ['Host: www.nettruyenmoi.com'], 1);

                    $html = str_get_html($content);

                    if ($html->find('#nt_listchapter nav ul li.row')) {

                        $chaps = $html->find('#nt_listchapter nav ul li.row');

                        $links = [];

                        for ($j = 0; $j < count($chaps); $j++) {
                            
                            $chap = isset($chaps[$j]) ? $chaps[$j]: null;
                            if ($chap) {
                                $link = $chap->find('div.chapter a', 0) ? $chap->find('div.chapter a', 0)->getAttribute('href') : '';
                                $chapter = $chap->find('div.chapter a', 0) ? $chap->find('div.chapter a', 0)->text() : '';

                                if ($link !== '' && $chapter !== '') $links[$chapter] = $link;
                            }
                            
                        }
                        if (count($links) > 0) {

                            foreach ($links as $chapter => $link) {

                                $content = $curl->getContent($link, ['Host: www.nettruyenmoi.com'], 1);

                                if ($content) {

                                    $html = str_get_html($content);

                                    if ($html->find('.reading-detail.box_doc', 0)) {
                                        $images = $html->find('.reading-detail.box_doc', 0)->find('.page-chapter');

                                        $pictures = [];

                                        if (isset($images) && count($images) > 0) {
                                            foreach ($images as $image) {
                                                if ($image->find('img', 0)) $pictures[] = $image->find('img', 0)->getAttribute('src');
                                            }
                                        }

                                        if (count($pictures) > 0) {
                                            // $data[$name] = implode(',', $pictures);

                                            $chapterNumber = null;

                                            if (strpos($chapter, ':') !== false) {
                                                $chapters = explode(':', $chapter);
                                                $chapterNumber = str_replace('Chapter ', '', $chapters[0]);
                                            }
                                            else {

                                                $chapterNumber = str_replace('Chapter ', '', $chapter);
                                            }

                                            $check = $db->query('select id from so1_manga_chapters where chapter = ? and mid = ?', [$chapterNumber, $mangaId])->fetchArray();

                                            if (isset($check['id'])) {
                                                $update = $db->query('update so1_manga_chapters set content = ?, sync = ? where id = ?', [implode(',', $pictures), 2, $check['id']]);
                                                echo "update manga $name - $mangaId chapter $chapterNumber success".PHP_EOL;
                                            }
                                            else {
                                                $insert = $db->query('insert into so1_manga_chapters (chapter, name, mid, manga, last_update, content, sync) values(?, ?, ?, ?, ?,?,?)', [$chapterNumber, 'Chapter '.$chapterNumber, $mangaId, $manga['slug'], date('Y-m-d h:i:s'), implode(',', $pictures), 2]);

                                                echo "insert manga $name - $mangaId chapter $chapterNumber success".PHP_EOL;
                                            }
                                        }
                                    }
                                }
                            }

                            $updateMangaStatus = $db->query('update so1_manga_mangas set craw = ? where id = ?', [1, $mangaId]);
                            $total = $total -1;
                            echo "-----------update manga $name - $mangaId  success------------".PHP_EOL;
                            echo "-----------manga left $total ------------".PHP_EOL;
                        }
                    }


                }
                
            }
        }
        else {
            $insert = $db->query("insert into so1_fail_jobs (mid, des) values (?,?)", [$manga['id'], 'not found story in nettruyen']);
        }
        sleep(1);
    }

    echo '<pre>';

    print_r($data);

    echo '</pre>';

}



