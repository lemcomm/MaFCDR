<?php
/**
 * DatePartFunction ::= "DATE_PART" "(" StringPrimary "," ArithmeticExpression ")"
 */

namespace BM2\SiteBundle\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;


class DatePart extends FunctionNode {

	public $partExpression = null;
	public $dateExpression = null;

	public function parse(Parser $parser) {
		$parser->match(Lexer::T_IDENTIFIER); // (2)
		$parser->match(Lexer::T_OPEN_PARENTHESIS); // (3)
		$this->partExpression = $parser->StringPrimary(); // (4)
		$parser->match(Lexer::T_COMMA); // (5)
		$this->dateExpression = $parser->ArithmeticExpression(); // (6)
		$parser->match(Lexer::T_CLOSE_PARENTHESIS); // (3)
	}

	public function getSql(SqlWalker $sqlWalker) {
		return 'DATE_PART(' .
			$this->partExpression->dispatch($sqlWalker) . ', ' .
			$this->dateExpression->dispatch($sqlWalker) .
		')'; // (7)
	}
}