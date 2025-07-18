<?php

namespace tokenizer;

use PHPUnit\Framework\TestCase;
use TeamTNT\TNTSearch\Tokenizer\ProductTokenizer;

class ProductTokenizerTest extends TestCase
{
    public function testTokenize()
    {
        $tokenizer = new ProductTokenizer;

        $text = "This is some text";
        $res = $tokenizer->tokenize($text);

        $this->assertContains("this", $res);
        $this->assertContains("text", $res);

        $text = "123 123 123";
        $res = $tokenizer->tokenize($text);
        $this->assertContains("123", $res);

        $text = "Hi! This text contains an test@email.com. Test's email 123.";
        $res = $tokenizer->tokenize($text);

        $this->assertContains("test's", $res);
        $this->assertContains("email", $res);
        $this->assertContains("contains", $res);
        $this->assertContains("123", $res);

        $text = "Superman (1941)";
        $res = $tokenizer->tokenize($text);
        $this->assertContains("superman", $res);
        $this->assertContains("(1941)", $res);

        $text = "čćž šđ";
        $res = $tokenizer->tokenize($text);
        $this->assertContains("čćž", $res);
        $this->assertContains("šđ", $res);
    }
}
