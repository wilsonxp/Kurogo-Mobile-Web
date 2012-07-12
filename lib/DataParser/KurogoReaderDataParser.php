<?php

includePackage('readability');

class KurogoReaderDataParser extends DataParser {
	private $tidyAvailable;
	private $baseUrl;
    
    public function parseData($html) {

        // check tidy function available
        if(function_exists('tidy_parse_string')) {
            $this->tidyAvailable = true;
        }
        $url = $this->getOption("readerUrl");
        $urlArray = parse_url($url);
        $this->baseUrl = $urlArray['scheme'] . "://" . $urlArray['host'];
    	$headers = $this->response->getHeaders();
    	if(isset($headers['Location'])) {
    		$this->baseUrl = $headers["Location"];
    	}

    	$html = $this->tidyClean($html);
        $readability = new Readability($html, $url);
        $readability->init();
        $title = $readability->getTitle()->textContent;
        $content = $readability->getContent()->innerHTML;
        /**
         * still need one more tidy clean, otherwise domdocument will not work properly
         */
        $content = $this->tidyClean($content);
        /**
         * use domdocument to fix relative urls
         */
        $content = $this->fixRelativeUrls($content);
        /**
         * if there is tidy support, then detect target source code from meta content
         */
        if($this->tidyAvailable) {
            $tidy = tidy_parse_string($content, array(), 'utf8');
            $head = $tidy->head();
            $charset = $this->findCharset($head->value);
            if(!empty($charset) && $charset != "utf-8") {
                $content = mb_convert_encoding($content, "utf-8", $charset);
            }
        }
        $article = array(
            'title' => $title,
            'content' => $content
        );
        return $article;
    }
    
    /**
     * tidyClean 
     * clean html source code
     * 
     * @param mixed $html 
     * @param array $options 
     * @access private
     * @return void
     */
    private function tidyClean($html, $options = array()) {
        if(!$this->tidyAvailable) {
            return $html;
        }
        $search = array (
            "'<script[^>]*?>.*?</script>'si",
            "'<style[^>]*?>.*?</style>'si"
        );
        $html = preg_replace($search, "", $html);
        if(empty($options)) {
            $options = array(
                'indent' => true,
                'show-body-only' => true
            );
        }
        $tidy = tidy_parse_string($html, $options, "utf8");
        $tidy->cleanRepair();
        return $tidy->value;
    }

    private function fixRelativeUrls($content) {
        /**
         * if there is no libxml support, then we can't fix the relative urls
         */
        if(!class_exists("DOMDocument")) {
            return $content;
        }
        $doc = new DOMDocument();
        /**
         * convert utf-8 to html-entities, otherwise the none acsii codes will get messed up
         * like chinese and 2 bytes symbols
         */
        $content = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");
        /**
         * there will be some waring disable them
         */
        @$doc->loadHTML($content);

        /**
         * fix <img /> relative urls
         */
        $imgNodes = $doc->getElementsByTagName('img');
        for($i = 0;$i < $imgNodes->length;$i ++) {
            $imgSrc = $imgNodes->item($i)->getAttribute("src");
            $urlArray = parse_url($imgSrc);
            if(!isset($urlArray['host'])) {
                if(strpos($imgSrc, '/') !== 0) {
                    $imgSrc = "/" . $imgSrc;
                }
                $imgNodes->item($i)->setAttribute("src", $this->baseUrl . $imgSrc);
            }
        }
        /**
         * fix <a></a> tag relative urls
         */
        $hrefNodes = $doc->getElementsByTagName('a');
        for($i = 0;$i < $hrefNodes->length;$i ++) {
            $href = $hrefNodes->item($i)->getAttribute("href");
            $urlArray = parse_url($href);
            if(!isset($urlArray['host'])) {
                if(strpos($href, '/') !== 0) {
                    $href = "/" . $href;
                }
                $hrefNodes->item($i)->setAttribute("href", $this->baseUrl . $href);
            }
        }

        $html = $doc->saveHTML();

        // saved DOM document always has header. remove it
        $body = preg_replace(
            array(
                "/^\<\!DOCTYPE.*?<html><body>/si",
                "!</body></html>$!si"
            ), "", $html);
        return $body;
    }

    /**
     * findCharset 
     * find charset from meta content
     * 
     * @param string $node 
     * @access private
     * @return void
     */
    private function findCharset($content) {
        preg_match("/<meta[^>]*?charset=([a-z|A-Z|0-9]*[\\-]*[0-9]*[\\-]*[0-9]*)[\\s|\\S]*/i", $content, $matches);
        if(isset($matches[1])) {
            return $matches[1];
        }else {
            return false;
        }
    }
}