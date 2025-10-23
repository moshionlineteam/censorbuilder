<?php

namespace Snipe\BanBuilder;

class CensorWords
{
    public $badwords;
    private $censorChecks = null;
    private $whiteList = [];
    private $whiteListPlaceHolder = ' {whiteList[i]} ';
    private $replacer;

    public function __construct()
    {
        $this->badwords = [];
        $this->replacer = '*';
        $this->setDictionary('en-us');
    }

    public function setDictionary($dictionary)
    {
        $this->badwords = $this->readBadWords($dictionary);
    }

    public function addDictionary($dictionary)
    {
        $this->badwords = array_merge($this->badwords, $this->readBadWords($dictionary));
    }

    public function addFromArray($words)
    {
        $badwords       = array_merge($this->badwords, $words);
        $this->badwords = array_keys(array_count_values($badwords));
    }

    private function readBadWords($dictionary)
    {
        $badwords     = [];
        $baseDictPath = __DIR__ . DIRECTORY_SEPARATOR . 'dict' . DIRECTORY_SEPARATOR;

        if (is_array($dictionary)) {
            foreach ($dictionary as $dictionary_file) {
                $badwords = array_merge($badwords, $this->readBadWords($dictionary_file));
            }
        }

        if (is_string($dictionary)) {
            if (file_exists($baseDictPath . $dictionary . '.php')) {
                include $baseDictPath . $dictionary . '.php';
            } elseif (file_exists($dictionary)) {
                include $dictionary;
            } else {
                throw new \RuntimeException('Dictionary file not found: ' . $dictionary);
            }
        }

        return array_keys(array_count_values($badwords));
    }

    public function addWhiteList(array $list)
    {
        foreach ($list as $value) {
            if (is_string($value) && !empty($value)) {
                $this->whiteList[]['word'] = $value;
            }
        }
    }

    private function replaceWhiteListed($string, $reverse = false)
    {
        foreach ($this->whiteList as $key => $list) {
            if ($reverse && !empty($this->whiteList[$key]['placeHolder'])) {
                $placeHolder = $this->whiteList[$key]['placeHolder'];
                $string      = str_replace($placeHolder, $list['word'], $string);
            } else {
                $placeHolder                          = str_replace('[i]', $key, $this->whiteListPlaceHolder);
                $this->whiteList[$key]['placeHolder'] = $placeHolder;
                $string                               = str_replace($list['word'], $placeHolder, $string);
            }
        }

        return $string;
    }

    public function setReplaceChar($replacer)
    {
        $this->replacer = $replacer;
    }

    public function randCensor($chars, $len)
    {
        return str_shuffle(
            str_repeat($chars, (int)($len / strlen($chars))) .
            substr($chars, 0, $len % strlen($chars))
        );
    }


    private function normalizeCustomPattern(string $word): string
    {
        if (strpos($word, '*') !== false || strpos($word, '+') !== false) {

            $word = preg_replace_callback('/([a-z])\*([a-z])/i', function ($m) {
                return $m[1] . '[^' . $m[2] . ']*' . $m[2];
            }, $word);

            while (preg_match('/([a-z])\*([a-z])/i', $word)) {
                $word = preg_replace_callback('/([a-z])\*([a-z])/i', function ($m) {
                    return $m[1] . '[^' . $m[2] . ']*' . $m[2];
                }, $word);
            }

            $word = preg_replace('/([a-z])\+/', '$1(?!$1)', $word);
        }

        return $word;
    }

    private function generateCensorChecks($fullWords = false)
    {
        $badwords = $this->badwords;

        $leet_replace = [
            'a' => '(a|a\.|a\-|4|@|Á|á|À|Â|à|Â|â|Ä|ä|Ã|ã|Å|å|α|Δ|Λ|λ)',
            'b' => '(b|b\.|b\-|8|\|3|ß|Β|β)',
            'c' => '(c|c\.|c\-|Ç|ç|¢|€|<|\(|{|©)',
            'd' => '(d|d\.|d\-|&part;|\|\)|Þ|þ|Ð|ð)',
            'e' => '(e|e\.|e\-|3|€|È|è|É|é|Ê|ê|∑)',
            'f' => '(f|f\.|f\-|ƒ)',
            'g' => '(q|g|g\.|g\-|6|9)',
            'h' => '(h|h\.|h\-|Η)',
            'i' => '(i|i\.|i\-|!|\||\]\[|]|1|∫|Ì|Í|Î|Ï|ì|í|î|ï)',
            'j' => '(j|j\.|j\-)',
            'k' => '(k|k\.|k\-|Κ|κ)',
            'l' => '(l|1\.|l\-|!|\||\]\[|]|£|∫|Ì|Í|Î|Ï)',
            'm' => '(m|m\.|m\-)',
            'n' => '(n|n\.|n\-|η|Ν|Π)',
            'o' => '(o|o\.|o\-|0|Ο|ο|Φ|¤|°|ø)',
            'p' => '(p|p\.|p\-|ρ|Ρ|¶|þ)',
            'q' => '(g|q|q\.|q\-)',
            'r' => '(r|r\.|r\-|®)',
            's' => '(s|s\.|s\-|5|\$|§)',
            't' => '(t|t\.|t\-|Τ|τ|7)',
            'u' => '(u|u\.|u\-|υ|µ)',
            'v' => '(v|v\.|v\-|υ|ν)',
            'w' => '(w|w\.|w\-|ω|ψ|Ψ)',
            'x' => '(x|x\.|x\-|Χ|χ)',
            'y' => '(y|y\.|y\-|¥|γ|ÿ|ý|Ÿ|Ý)',
            'z' => '(z|z\.|z\-|Ζ)',
        ];

        $censorChecks = [];
        foreach ($badwords as $word) {
            $wordPattern = $this->normalizeCustomPattern($word);

            // If it looks like a regex pattern, skip leet replacement
            if (preg_match('/[()[\]^$|]/', $wordPattern)) {
                $pattern = $wordPattern;
            } else {
                $pattern = str_ireplace(array_keys($leet_replace), array_values($leet_replace), $wordPattern);
            }

            $censorChecks[] = $fullWords
                ? '/\b' . $pattern . '\b/i'
                : '/' . $pattern . '/i';
        }

        $this->censorChecks = $censorChecks;
    }

    public function censorString($string, $fullWords = false)
    {
        if (!$this->censorChecks) {
            $this->generateCensorChecks($fullWords);
        }

        $anThis            = &$this;
        $counter           = 0;
        $match             = [];
        $newstring         = [];
        $newstring['orig'] = html_entity_decode($string);
        $original          = $this->replaceWhiteListed($newstring['orig']);

        $newstring['clean'] = preg_replace_callback(
            $this->censorChecks,
            function ($matches) use (&$anThis, &$counter, &$match) {
                $match[$counter++] = $matches[0];

                return (strlen($anThis->replacer) === 1)
                    ? str_repeat($anThis->replacer, strlen($matches[0]))
                    : $anThis->randCensor($anThis->replacer, strlen($matches[0]));
            },
            $original
        );

        $newstring['clean']   = $this->replaceWhiteListed($newstring['clean'], true);
        $newstring['matched'] = $match;

        return $newstring;
    }
}
