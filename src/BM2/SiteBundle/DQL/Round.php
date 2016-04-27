<?php
/**
 * RoundFunction ::= "ROUND" "(" ArithmeticExpression ")"
 */

namespace BM2\SiteBundle\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


class Round extends FunctionNode {

    private $arithmeticExpression;

    public function parse(Parser $parser) {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->arithmeticExpression = $parser->SimpleArithmeticExpression();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker) {
        return 'ROUND(' .
            $this->arithmeticExpression->dispatch($sqlWalker) .
        ')';
    }
}
