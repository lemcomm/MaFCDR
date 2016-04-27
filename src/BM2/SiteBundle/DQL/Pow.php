<?php
/**
 * PowFunction ::= "POW" "(" ArithmeticExpression "," ArithmeticExpression ")"
 */

namespace BM2\SiteBundle\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


class Pow extends FunctionNode {

	public $firstExpression = null;
	public $secondExpression = null;

	public function parse(Parser $parser) {
		$parser->match(Lexer::T_IDENTIFIER);
		$parser->match(Lexer::T_OPEN_PARENTHESIS);
		$this->firstExpression = $parser->ArithmeticExpression();
		$parser->match(Lexer::T_COMMA);
		$this->secondExpression = $parser->ArithmeticExpression();
		$parser->match(Lexer::T_CLOSE_PARENTHESIS);
	}

	public function getSql(SqlWalker $sqlWalker) {
		return 'POW(' .
			$this->firstExpression->dispatch($sqlWalker) . ', ' .
			$this->secondExpression->dispatch($sqlWalker) .
		')';
	}
}