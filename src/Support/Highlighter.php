<?php

namespace TeamTNT\TNTSearch\Support;

use TeamTNT\TNTSearch\Tokenizer\Tokenizer;
use TeamTNT\TNTSearch\Tokenizer\TokenizerInterface;

class Highlighter
{
    protected $options = [
        'simple' => false,
        'wholeWord' => true,
        'caseSensitive' => false,
        'stripLinks' => false,
        'tagOptions' => [
            // 'class' => 'search-term',             // Example
            // 'title' => 'You searched for this.',  // Example
            // 'data-toggle' => 'tooltip',           // Example
        ],
    ];

    protected TokenizerInterface $tokenizer;

    public function __construct(TokenizerInterface $tokenizer = null)
    {
        $this->tokenizer = $tokenizer ?? new Tokenizer();
    }

    /**
     * @param        $text
     * @param        $needle
     * @param string $tag
     * @param array $options
     *
     * @return string
     */
    public function highlight($text, string $needle, string $tag = 'em', array $options = [])
    {
        $this->options = array_merge($this->options, $options);

        $tagAttributes = '';
        if (count($this->options['tagOptions'])) {
            foreach ($this->options['tagOptions'] as $attr => $value) {
                $tagAttributes .= $attr . '="' . $value . '" ';
            }
            $tagAttributes = ' ' . trim($tagAttributes);
        }

        $highlight = '<' . $tag . $tagAttributes . '>\1</' . $tag . '>';
        $needle = preg_split($this->tokenizer->getPattern(), $needle, -1, PREG_SPLIT_NO_EMPTY);

        // Select pattern to use
        if ($this->options['simple']) {
            $pattern = '#(%s)#';
            $sl_pattern = '#(%s)#';
        } else {
            $pattern = '#(?!<.*?)(%s)(?![^<>]*?>)#';
            $sl_pattern = '#<a\s(?:.*?)>(%s)</a>#';
        }

        // Add Forgotten Unicode
        $pattern .= 'u';

        // Case sensitivity
        if (!($this->options['caseSensitive'])) {
            $pattern .= 'i';
            $sl_pattern .= 'i';
        }

        $needle = (array)$needle;
        foreach ($needle as $needle_s) {
            $needle_s = preg_quote($needle_s);

            // Escape needle with optional whole word check
            if ($this->options['wholeWord']) {
                $needle_s = '\b' . $needle_s . '\b';
            }

            // Strip links
            if ($this->options['stripLinks']) {
                $sl_regex = sprintf($sl_pattern, $needle_s);
                $text = preg_replace($sl_regex, '\1', $text);
            }

            $regex = sprintf($pattern, $needle_s);
            $text = preg_replace($regex, $highlight, $text);
        }

        return $text;
    }

    /**
     * find the locations of each of the words
     * Nothing exciting here. The array_unique is required
     * unless you decide to make the words unique before passing in
     *
     * @param array $words
     * @param string $fulltext
     *
     * @return array
     */
    public function _extractLocations(array $words, string $fulltext)
    {
        $locations = [];
        foreach ($words as $word) {
            $wordlen = mb_strlen($word);
            $loc = mb_stripos($fulltext, $word);
            while ($loc !== false) {
                $locations[] = $loc;
                $loc = mb_stripos($fulltext, $word, $loc + $wordlen);
            }
        }
        $locations = array_unique($locations);
        sort($locations);

        return $locations;
    }

    /**
     * Work out which is the most relevant portion to display
     * This is done by looping over each match and finding the smallest distance between two found
     * strings. The idea being that the closer the terms are the better match the snippet would be.
     * When checking for matches we only change the location if there is a better match.
     * The only exception is where we have only two matches in which case we just take the
     * first as will be equally distant.
     *
     * @param array $locations
     * @param int $prevcount
     *
     * @return int
     */
    public function _determineSnipLocation(array $locations, int $prevcount)
    {
        if (!isset($locations[0])) {
            return -1;
        }

        // If we only have 1 match we dont actually do the for loop so set to the first
        $startpos = $locations[0];
        $loccount = count($locations);
        $smallestdiff = PHP_INT_MAX;
        // If we only have 2 skip as its probably equally relevant
        if (count($locations) > 2) {
            // skip the first as we check 1 behind
            for ($i = 1; $i < $loccount; $i++) {
                if ($i == $loccount - 1) {
                    // at the end
                    $diff = $locations[$i] - $locations[$i - 1];
                } else {
                    $diff = $locations[$i + 1] - $locations[$i];
                }

                if ($smallestdiff > $diff) {
                    $smallestdiff = $diff;
                    $startpos = $locations[$i];
                }
            }
        }

        $startpos = $startpos > $prevcount ? $startpos - $prevcount : 0;
        return $startpos;
    }

    /**
     * 1/6 ratio on prevcount tends to work pretty well and puts the terms
     * in the middle of the extract
     *
     * @param string $words
     * @param string $fulltext
     * @param int $rellength
     * @param int $prevcount
     * @param string $indicator
     *
     * @return bool|string
     */
    public function extractRelevant(
        string $words,
        string $fulltext,
        int $rellength = 300,
        int $prevcount = 50,
        string $indicator = '...'
    ) {
        $words = preg_split($this->tokenizer->getPattern(), $words, -1, PREG_SPLIT_NO_EMPTY);
        $textlength = mb_strlen($fulltext);
        if ($textlength <= $rellength) {
            return $fulltext;
        }

        $locations = $this->_extractLocations($words, $fulltext);
        $startpos = $this->_determineSnipLocation($locations, $prevcount);
        // if we are going to snip too much...
        if ($textlength - $startpos < $rellength) {
            $startpos = $startpos - ($textlength - $startpos) / 2;
            $startpos = max($startpos, 0);
        }

        // in case no match is found, reset position for proper math below
        if ($startpos == -1) {
            $startpos = 0;
        }

        $reltext = mb_substr($fulltext, $startpos, $rellength);
        preg_match_all($this->tokenizer->getPattern(), $reltext, $offset, PREG_OFFSET_CAPTURE);
        // since PREG_OFFSET_CAPTURE returns offset in bytes we have to use mb_strlen(substr()) hack here
        $last = mb_strlen(substr($reltext, 0, end($offset[0])[1]));
        $first = mb_strlen(substr($reltext, 0, $offset[0][0][1]));

        // if no match is found, just return first $rellength characters without the last word
        if (empty($locations)) {
            return mb_substr($reltext, 0, $last) . $indicator;
        }

        // check to ensure we dont snip the last word if thats the match
        if ($startpos + $rellength < $textlength) {
            $reltext = mb_substr($reltext, 0, $last) . $indicator; // remove last word
        }

        // If we trimmed from the front add ...
        if ($startpos != 0) {
            $reltext = $indicator . mb_substr($reltext, $first + 1); // remove first word
        }

        return $reltext;
    }
}
