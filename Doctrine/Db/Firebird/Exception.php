<?php
/* 
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.phpdoctrine.com>.
 */
Doctrine::autoload('Doctrine_Db_Exception');
/**
 * Doctrine_Db_Firebird_Exception
 *
 * @package     Doctrine
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lorenzo Alberton <l.alberton@quipo.it> (PEAR MDB2 Interbase driver)
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 */
class Doctrine_Db_Firebird_Exception extends Doctrine_Db_Exception { 
    /**
     * @var array $errorCodeMap         an array that is used for determining portable
     *                                  error code from a native database error code
     */
    protected static $errorCodeMap = array(
                                    -104 => Doctrine_Db::ERR_SYNTAX,
                                    -150 => Doctrine_Db::ERR_ACCESS_VIOLATION,
                                    -151 => Doctrine_Db::ERR_ACCESS_VIOLATION,
                                    -155 => Doctrine_Db::ERR_NOSUCHTABLE,
                                    -157 => Doctrine_Db::ERR_NOSUCHFIELD,
                                    -158 => Doctrine_Db::ERR_VALUE_COUNT_ON_ROW,
                                    -170 => Doctrine_Db::ERR_MISMATCH,
                                    -171 => Doctrine_Db::ERR_MISMATCH,
                                    -172 => Doctrine_Db::ERR_INVALID,
                                    // -204 =>  // Covers too many errors, need to use regex on msg
                                    -205 => Doctrine_Db::ERR_NOSUCHFIELD,
                                    -206 => Doctrine_Db::ERR_NOSUCHFIELD,
                                    -208 => Doctrine_Db::ERR_INVALID,
                                    -219 => Doctrine_Db::ERR_NOSUCHTABLE,
                                    -297 => Doctrine_Db::ERR_CONSTRAINT,
                                    -303 => Doctrine_Db::ERR_INVALID,
                                    -413 => Doctrine_Db::ERR_INVALID_NUMBER,
                                    -530 => Doctrine_Db::ERR_CONSTRAINT,
                                    -551 => Doctrine_Db::ERR_ACCESS_VIOLATION,
                                    -552 => Doctrine_Db::ERR_ACCESS_VIOLATION,
                                    // -607 =>  // Covers too many errors, need to use regex on msg
                                    -625 => Doctrine_Db::ERR_CONSTRAINT_NOT_NULL,
                                    -803 => Doctrine_Db::ERR_CONSTRAINT,
                                    -804 => Doctrine_Db::ERR_VALUE_COUNT_ON_ROW,
                                    -904 => Doctrine_Db::ERR_CONNECT_FAILED,
                                    -922 => Doctrine_Db::ERR_NOSUCHDB,
                                    -923 => Doctrine_Db::ERR_CONNECT_FAILED,
                                    -924 => Doctrine_Db::ERR_CONNECT_FAILED
                                    );
    /**
     * @var array $errorRegexps         an array that is used for determining portable 
     *                                  error code from a native database error message
     */
    protected static $errorRegexps = array(
                                    '/generator .* is not defined/'
                                        => Doctrine_Db::ERR_SYNTAX,  // for compat. w ibase_errcode()
                                    '/table.*(not exist|not found|unknown)/i'
                                        => Doctrine_Db::ERR_NOSUCHTABLE,  
                                    '/table .* already exists/i'
                                        => Doctrine_Db::ERR_ALREADY_EXISTS, 
                                    '/unsuccessful metadata update .* failed attempt to store duplicate value/i'
                                        => Doctrine_Db::ERR_ALREADY_EXISTS,
                                    '/unsuccessful metadata update .* not found/i'
                                        => Doctrine_Db::ERR_NOT_FOUND,
                                    '/validation error for column .* value "\*\*\* null/i'
                                        => Doctrine_Db::ERR_CONSTRAINT_NOT_NULL,
                                    '/violation of [\w ]+ constraint/i'
                                        => Doctrine_Db::ERR_CONSTRAINT,
                                    '/conversion error from string/i'
                                        => Doctrine_Db::ERR_INVALID_NUMBER,
                                    '/no permission for/i'
                                        => Doctrine_Db::ERR_ACCESS_VIOLATION,
                                    '/arithmetic exception, numeric overflow, or string truncation/i'
                                        => Doctrine_Db::ERR_INVALID,
                                    '/table unknown/i'                     
                                        => Doctrine::ERR_NOSUCHTABLE,
                                    );
    /**
     * This method checks if native error code/message can be 
     * converted into a portable code and then adds this 
     * portable error code to errorInfo array and returns the modified array
     *
     * the portable error code is added at the end of array
     *
     * @param array $errorInfo      error info array
     * @since 1.0
     * @return array
     */
    public function processErrorInfo(array $errorInfo) {
            /**
            // todo: are the following lines needed?
            // memo for the interbase php module hackers: we need something similar
            // to mysql_errno() to retrieve error codes instead of this ugly hack
            if (preg_match('/^([^0-9\-]+)([0-9\-]+)\s+(.*)$/', $native_msg, $m)) {
                $native_code = (int)$m[2];
            } else {
                $native_code = null;
            }
            */

        foreach(self::$errorRegexps as $regexp => $code) {
            if (preg_match($regexp, $errorInfo[2])) {
                $errorInfo[3] = $code;
                break;
            }
        }
        if(isset(self::$errorCodeMap[$errorInfo[1]]))
            $errorInfo[3] = self::$errorCodeMap[$errorInfo[1]];

        return $errorInfo;
    }
}
