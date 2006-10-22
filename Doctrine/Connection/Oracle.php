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
Doctrine::autoload('Doctrine_Connection');
/**
 * Doctrine_Connection_Oracle
 *
 * @package     Doctrine ORM
 * @url         www.phpdoctrine.com
 * @license     LGPL
 */
class Doctrine_Connection_Oracle extends Doctrine_Connection {
    /**
     * Adds an driver-specific LIMIT clause to the query
     *
     * @param string $query
     * @param mixed $limit
     * @param mixed $offset
     */
    public function modifyLimitQuery($query,$limit,$offset) {
        $e      = explode("select ",strtolower($query));
        $e2     = explode(" from ",$e[1]);
        $fields = $e2[0];

        $query = "SELECT $fields FROM (SELECT rownum as linenum, $fields FROM ($query) WHERE rownum <= ($offset + $limit)) WHERE linenum >= ".++$offset;
        return $query;
    }
    /**
     * Set the transacton isolation level.
     *
     * @param   string  standard isolation level
     *                  READ UNCOMMITTED (allows dirty reads)
     *                  READ COMMITTED (prevents dirty reads)
     *                  REPEATABLE READ (prevents nonrepeatable reads)
     *                  SERIALIZABLE (prevents phantom reads)
     * @return  mixed   MDB2_OK on success, a MDB2 error on failure
     */
    function setTransactionIsolation($isolation) {
        switch ($isolation) {
            case 'READ UNCOMMITTED':
                $isolation = 'READ COMMITTED';
            case 'READ COMMITTED':
            case 'REPEATABLE READ':
                $isolation = 'SERIALIZABLE';
            case 'SERIALIZABLE':
                break;
            default:
                throw new Doctrine_Connection_Oracle_Exception('Isolation level ' . $isolation . 'is not supported.');
        }

        $query = 'ALTER SESSION ISOLATION LEVEL ' . $isolation;
        return $this->dbh->query($query);
    }
    /**
     * returns the next value in the given sequence
     * @param string $sequence
     * @return integer
     */
    public function getNextID($sequence) {
        $stmt = $this->query("SELECT $sequence.nextval FROM dual");
        $data = $stmt->fetch(PDO::FETCH_NUM);
        return $data[0];
    }
    /**
     * Return string to call a variable with the current timestamp inside an SQL statement
     * There are three special variables for current date and time:
     * - CURRENT_TIMESTAMP (date and time, TIMESTAMP type)
     * - CURRENT_DATE (date, DATE type)
     * - CURRENT_TIME (time, TIME type)
     *
     * @return string to call a variable with the current timestamp
     * @access public
     */
    function now($type = 'timestamp')
    {
        switch ($type) {
        case 'date':
        case 'time':
        case 'timestamp':
        default:
            return 'TO_CHAR(CURRENT_TIMESTAMP, \'YYYY-MM-DD HH24:MI:SS\')';
        }
    }
    /**
     * substring
     *
     * @return string           SQL substring function with given parameters
     */
    function substring($value, $position = 1, $length = null) {
        if($length !== null)
            return "SUBSTR($value, $position, $length)";

        return "SUBSTR($value, $position)";
    }
    /**
     * random
     *
     * @return string           an oracle SQL string that generates a float between 0 and 1
     */
    function random() {
        return 'dbms_random.value';
    }
}

