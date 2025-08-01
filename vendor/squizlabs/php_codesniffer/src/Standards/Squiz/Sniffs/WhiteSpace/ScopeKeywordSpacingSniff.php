<?php
/**
 * Ensure there is a single space after scope keywords.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ScopeKeywordSpacingSniff implements Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array<int|string>
     */
    public function register()
    {
        $register  = Tokens::$methodPrefixes;
        $register += Tokens::$scopeModifiers;
        $register[T_READONLY] = T_READONLY;
        return $register;

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $nextNonWhitespace = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($nextNonWhitespace === false) {
            // Parse error/live coding. Bow out.
            return;
        }

        $prevToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($tokens[$stackPtr]['code'] === T_STATIC) {
            if (($nextToken === false || $tokens[$nextToken]['code'] === T_DOUBLE_COLON)
                || $tokens[$prevToken]['code'] === T_NEW
            ) {
                // Late static binding, e.g., static:: OR new static() usage or live coding.
                return;
            }

            if ($prevToken !== false
                && $tokens[$prevToken]['code'] === T_TYPE_UNION
            ) {
                // Not a scope keyword, but a union return type.
                return;
            }

            if ($prevToken !== false
                && $tokens[$prevToken]['code'] === T_NULLABLE
            ) {
                // Not a scope keyword, but a return type.
                return;
            }

            if ($prevToken !== false
                && $tokens[$prevToken]['code'] === T_COLON
            ) {
                $prevPrevToken = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prevToken - 1), null, true);
                if ($prevPrevToken !== false
                    && $tokens[$prevPrevToken]['code'] === T_CLOSE_PARENTHESIS
                ) {
                    // Not a scope keyword, but a return type.
                    return;
                }
            }
        }//end if

        if ($tokens[$prevToken]['code'] === T_AS) {
            // Trait visibility change, e.g., "use HelloWorld { sayHello as private; }".
            return;
        }

        $isInFunctionDeclaration = false;
        if (empty($tokens[$stackPtr]['nested_parenthesis']) === false) {
            // Check if this is PHP 8.0 constructor property promotion.
            // In that case, we can't have multi-property definitions.
            $nestedParens    = $tokens[$stackPtr]['nested_parenthesis'];
            $lastCloseParens = end($nestedParens);
            if (isset($tokens[$lastCloseParens]['parenthesis_owner']) === true
                && $tokens[$tokens[$lastCloseParens]['parenthesis_owner']]['code'] === T_FUNCTION
            ) {
                $isInFunctionDeclaration = true;
            }
        }

        if ($nextToken !== false
            && $tokens[$nextToken]['code'] === T_VARIABLE
            && $isInFunctionDeclaration === false
        ) {
            $endOfStatement = $phpcsFile->findNext(T_SEMICOLON, ($nextToken + 1));
            if ($endOfStatement === false) {
                // Live coding.
                return;
            }

            $multiProperty = $phpcsFile->findNext(T_VARIABLE, ($nextToken + 1), $endOfStatement);
            if ($multiProperty !== false
                && $tokens[$stackPtr]['line'] !== $tokens[$nextToken]['line']
                && $tokens[$nextToken]['line'] !== $tokens[$endOfStatement]['line']
            ) {
                // Allow for multiple properties definitions to each be on their own line.
                return;
            }
        }

        if ($tokens[($stackPtr + 1)]['code'] !== T_WHITESPACE) {
            $spacing = 0;
        } else {
            if ($tokens[($stackPtr + 2)]['line'] !== $tokens[$stackPtr]['line']) {
                $spacing = 'newline';
            } else {
                $spacing = $tokens[($stackPtr + 1)]['length'];
            }
        }

        if ($spacing !== 1) {
            $error = 'Scope keyword "%s" must be followed by a single space; found %s';
            $data  = [
                $tokens[$stackPtr]['content'],
                $spacing,
            ];

            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'Incorrect', $data);
            if ($fix === true) {
                if ($spacing === 0) {
                    $phpcsFile->fixer->addContent($stackPtr, ' ');
                } else {
                    $phpcsFile->fixer->beginChangeset();

                    for ($i = ($stackPtr + 2); $i < $phpcsFile->numTokens; $i++) {
                        if (isset($tokens[$i]) === false || $tokens[$i]['code'] !== T_WHITESPACE) {
                            break;
                        }

                        $phpcsFile->fixer->replaceToken($i, '');
                    }

                    $phpcsFile->fixer->replaceToken(($stackPtr + 1), ' ');
                    $phpcsFile->fixer->endChangeset();
                }
            }//end if
        }//end if

    }//end process()


}//end class
