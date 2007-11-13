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
Doctrine::autoload('Doctrine_Record_Abstract');
/**
 * Doctrine_Template
 *
 * @package     Doctrine
 * @subpackage  Template
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 */
class Doctrine_Template extends Doctrine_Record_Abstract
{
    /**
     * @param Doctrine_Record $_invoker     the record that invoked the last delegated call
     */
    protected $_invoker;
    
    
    protected $_plugin;

    /**
     * setTable
     *
     * @param Doctrine_Table $_table        the table object this Template belongs to
     */
    public function setTable(Doctrine_Table $table)
    {
        $this->_table = $table;
    }

    /**
     * getTable
     * returns the associated table object
     *
     * @return Doctrine_Table               the associated table object
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * setInvoker
     *
     * sets the last used invoker
     *
     * @param Doctrine_Record $invoker      the record that invoked the last delegated call
     * @return Doctrine_Template            this object
     */
    public function setInvoker(Doctrine_Record $invoker)
    {
        $this->_invoker = $invoker;
    }

    /**
     * setInvoker
     * returns the last used invoker
     *
     * @return Doctrine_Record              the record that invoked the last delegated call
     */
    public function getInvoker()
    {
        return $this->_invoker;
    }

    public function addChild(Doctrine_Template $template)
    {
        $this->_plugin->addChild($template);
        
        return $this;
    }


    public function getPlugin()
    {
        return $this->_plugin;
    }

    public function get($name) 
    {
        throw new Doctrine_Exception("Templates doesn't support accessors.");
    }
    public function set($name, $value)
    {
        throw new Doctrine_Exception("Templates doesn't support accessors.");
    }
    public function setUp()
    {

    }

    public function setTableDefinition()
    {

    }
}
