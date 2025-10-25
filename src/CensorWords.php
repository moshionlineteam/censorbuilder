<?php

namespace Snipe\BanBuilder;

class CensorWords
{
    public $badwords = [];
    private $censorChecks = null;
    private $whiteList = [];
    private $whiteListPlaceHolder = ' {whiteList[i]} ';
    private $replacer = '*';
    private $jsonPath = null;

public function __construct($jsonPath = null)
{
    if ($jsonPath !== null) {
        $this->jsonPath = $jsonPath;
        $this->loadMainJson($this->jsonPath);
    }
}

public function json(string $path): self
{
    $this->jsonPath = $path;
    $this->loadMainJson($this->jsonPath);
    return $this;
}

private function loadMainJson(?string $jsonPath = null): void
{
    if ($this->jsonPath !== null && $jsonPath === null) {
        $jsonPath = $this->jsonPath;
    }

    if ($jsonPath === null) {
        $jsonPath = $this->getProjectRoot() . '/config/censor.json';
    }

    $jsonPath = realpath($jsonPath);
    if ($jsonPath === false || !file_exists($jsonPath)) {
        throw new \RuntimeException('Missing censor.json file at: ' . ($jsonPath ?: 'unknown path'));
    }

    $data = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($data)) {
        throw new \RuntimeException('Invalid censor.json format');
    }

    $this->badwords = array_values(array_unique(array_filter($data)));
}

private function getProjectRoot(): string
{
    $dir = __DIR__;
    while (!file_exists($dir . '/composer.json') && dirname($dir) !== $dir) {
        $dir = dirname($dir);
    }
    return $dir;
}

    public function addFromArray(array $words)
    {
        $this->badwords = array_values(array_unique(array_merge($this->badwords, $words)));
    }

    public function addWhiteList(array $list)
    {
        foreach ($list as $value) {
            if (is_string($value) && $value !== '') {
                $this->whiteList[]['word'] = $value;
            }
        }
    }

    private function replaceWhiteListed($string, $reverse = false)
    {
        foreach ($this->whiteList as $key => $list) {
            if ($reverse && !empty($this->whiteList[$key]['placeHolder'])) {
                $string = str_replace($this->whiteList[$key]['placeHolder'], $list['word'], $string);
            } else {
                $placeHolder = str_replace('[i]', $key, $this->whiteListPlaceHolder);
                $this->whiteList[$key]['placeHolder'] = $placeHolder;
                $string = str_replace($list['word'], $placeHolder, $string);
            }
        }
        return $string;
    }

    public function setReplaceChar($replacer)
    {
        $this->replacer = $replacer;
    }

    private function expandWordToFlexibleRegex(string $word): string
    {
        $pattern = '';
        $letters = str_split($word);

        foreach ($letters as $char) {
            $escaped = preg_quote($char, '/');
            $pattern .= '(' . $escaped . '+)';
        }

        return $pattern;
    }

private function generateCensorChecks($fullWords = false)
{
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

    foreach ($this->badwords as $word) {
        $word = trim($word);
        if (preg_match('/[()\[\]\+\*\|]/', $word)) {
            $pattern = '/' . $word . '/i';
        } else {
            $flexPattern = $this->expandWordToFlexibleRegex($word);
            $pattern = str_ireplace(array_keys($leet_replace), array_values($leet_replace), $flexPattern);
            $pattern = $fullWords ? '/\b' . $pattern . '\b/i' : '/' . $pattern . '/i';
        }

        $censorChecks[] = $pattern;
    }

    $this->censorChecks = $censorChecks;
}

public function censorString($string, $fullWords = false)
{
    if (!$this->censorChecks) {
        $this->generateCensorChecks($fullWords);
    }

    $original = html_entity_decode($string);
    $matches = [];

    $sanitized = preg_replace('/[^a-z0-9 _-]+/i', '', strtolower($original));
    $clean = $original;

    foreach ($this->censorChecks as $pattern) {
        $pattern = trim($pattern);
        $pattern = preg_replace('/^\/|\/[a-z-]*$/i', '', $pattern);

        if (preg_match_all('/' . $pattern . '/i', $sanitized, $found)) {
            foreach ($found[0] as $match) {
                $matches[] = $match;

                
                $loosePattern = '/' . preg_replace(
                    '/([a-z0-9])/i',
                    '$1[^a-z0-9 ]{0,26}',
                    $match
                ) . '/i';


                $clean = preg_replace_callback(
                    $loosePattern,
                    function ($m) {
                        return str_repeat($this->replacer, strlen($m[0]));
                    },
                    $clean
                );
            }
        }
    }

    return [
        'orig' => $string,
        'clean' => $clean,
        'matched' => array_unique($matches),
    ];
}

}