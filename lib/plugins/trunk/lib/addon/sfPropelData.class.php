<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This class is the Propel implementation of sfData.  It interacts with the data source
 * and loads data.
 *
 * @package    symfony
 * @subpackage propel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id$
 */
class sfPropelData extends sfData
{
  protected
    $deletedClasses = array(),
    $con            = null;

  /**
   * Loads data from a file or directory into a Propel data source
   *
   * @see sfPropelData::loadData()
   *
   * @param mixed   $directoryOrFile  A file or directory path or an array of files or directories
   * @param string  $connectionName   The Propel connection name, default 'propel'
   *
   * @throws Exception If the database throws an error, rollback transaction and rethrows exception
   */
  public function loadData($directoryOrFile = null, $connectionName = 'propel')
  {
    $files = $this->getFiles($directoryOrFile);

    // load map classes
    $this->loadMapBuilders();
    $this->dbMap = Propel::getDatabaseMap($connectionName);

    // wrap all database operations in a single transaction
    $this->con = Propel::getConnection($connectionName);
    try
    {
      $this->con->beginTransaction();

      $this->doDeleteCurrentData($files);

      $this->doLoadData($files);

      $this->con->commit();
    }
    catch (Exception $e)
    {
      $this->con->rollBack();
      throw $e;
    }
  }

  /**
   * Implements the abstract loadDataFromArray method and loads the data using the generated data model.
   *
   * @param array   $data  The data to be loaded into the data source
   *
   * @throws Exception If data is unnamed.
   * @throws sfException If an object defined in the model does not exist in the data
   * @throws sfException If a column that does not exist is referenced
   */
  public function loadDataFromArray($data)
  {
    if ($data === null)
    {
      // no data
      return;
    }

    foreach ($data as $class => $datas)
    {
      $class = trim($class);

      $tableMap = $this->dbMap->getTable(constant(constant($class.'::PEER').'::TABLE_NAME'));

      $column_names = call_user_func_array(array(constant($class.'::PEER'), 'getFieldNames'), array(BasePeer::TYPE_FIELDNAME));

      // iterate through datas for this class
      // might have been empty just for force a table to be emptied on import
      if (!is_array($datas))
      {
        continue;
      }

      foreach ($datas as $key => $data)
      {
        // create a new entry in the database
        if (!class_exists($class))
        {
          throw new InvalidArgumentException(sprintf('Unknown class "%s".', $class));
        }

        $obj = new $class();

        if (!$obj instanceof BaseObject)
        {
          throw new RuntimeException(sprintf('The class "%s" is not a Propel class. This probably means there is already a class named "%s" somewhere in symfony or in your project.', $class, $class));
        }

        if (!is_array($data))
        {
          throw new InvalidArgumentException(sprintf('You must give a name for each fixture data entry (class %s).', $class));
        }

        foreach ($data as $name => $value)
        {
          if (is_array($value) && 's' == substr($name, -1))
          {
            // many to many relationship
            $this->loadMany2Many($obj, substr($name, 0, -1), $value);

            continue;
          }

          $isARealColumn = true;
          try
          {
            $column = $tableMap->getColumn($name);
          }
          catch (PropelException $e)
          {
            $isARealColumn = false;
          }

          // foreign key?
          if ($isARealColumn)
          {
            if ($column->isForeignKey() && !is_null($value))
            {
              $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
              if (!isset($this->object_references[$relatedTable->getPhpName().'_'.$value]))
              {
                throw new InvalidArgumentException(sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedTable->getPhpName()));
              }
              $value = $this->object_references[$relatedTable->getPhpName().'_'.$value]->getByName($column->getRelatedName(), BasePeer::TYPE_COLNAME);
            }
          }

          if (false !== $pos = array_search($name, $column_names))
          {
            $obj->setByPosition($pos, $value);
          }
          else if (is_callable(array($obj, $method = 'set'.sfInflector::camelize($name))))
          {
            $obj->$method($value);
          }
          else
          {
            throw new InvalidArgumentException(sprintf('Column "%s" does not exist for class "%s".', $name, $class));
          }
        }
        $obj->save($this->con);

        // save the object for future reference
        if (method_exists($obj, 'getPrimaryKey'))
        {
          $this->object_references[Propel::importClass(constant(constant($class.'::PEER').'::CLASS_DEFAULT')).'_'.$key] = $obj;
        }
      }
    }
  }

  /**
   * Loads many to many objects.
   *
   * @param BaseObject $obj               A Propel object
   * @param string     $middleTableName   The middle table name
   * @param array      $values            An array of values
   */
  protected function loadMany2Many($obj, $middleTableName, $values)
  {
    $middleTable = $this->dbMap->getTable($middleTableName);
    $middleClass = $middleTable->getPhpName();
    foreach ($middleTable->getColumns()  as $column)
    {
      if ($column->isForeignKey() && constant(constant(get_class($obj).'::PEER').'::TABLE_NAME') != $column->getRelatedTableName())
      {
        $relatedClass = $this->dbMap->getTable($column->getRelatedTableName())->getPhpName();
        break;
      }
    }

    if (!isset($relatedClass))
    {
      throw new InvalidArgumentException(sprintf('Unable to find the many-to-many relationship for object "%s".', get_class($obj)));
    }

    $setter = 'set'.get_class($obj);
    $relatedSetter = 'set'.$relatedClass;

    foreach ($values as $value)
    {
      if (!isset($this->object_references[$relatedClass.'_'.$value]))
      {
        throw new InvalidArgumentException(sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedClass));
      }

      $middle = new $middleClass();
      $middle->$setter($obj);
      $middle->$relatedSetter($this->object_references[$relatedClass.'_'.$value]);
      $middle->save();
    }
  }

  /**
   * Clears existing data from the data source by reading the fixture files
   * and deleting the existing data for only those classes that are mentioned
   * in the fixtures.
   *
   * @param array $files The list of YAML files.
   *
   * @throws sfException If a class mentioned in a fixture can not be found
   */
  protected function doDeleteCurrentData($files)
  {
    // delete all current datas in database
    if (!$this->deleteCurrentData)
    {
      return;
    }

    rsort($files);
    foreach ($files as $file)
    {
      $data = sfYaml::load($file);

      if ($data === null)
      {
        // no data
        continue;
      }

      $classes = array_keys($data);
      foreach (array_reverse($classes) as $class)
      {
        $class = trim($class);
        if (in_array($class, $this->deletedClasses))
        {
          continue;
        }

        // Check that peer class exists before calling doDeleteAll()
        if (!class_exists(constant($class.'::PEER')))
        {
          throw new InvalidArgumentException(sprintf('Unknown class "%sPeer".', $class));
        }

        call_user_func(array(constant($class.'::PEER'), 'doDeleteAll'), $this->con);

        $this->deletedClasses[] = $class;
      }
    }
  }

  /**
   * Loads all map builders.
   *
   * @throws sfException If the class cannot be found
   */
  protected function loadMapBuilders()
  {
    $files = sfFinder::type('file')->name('*MapBuilder.php')->in(sfProjectConfiguration::getActive()->getModelDirs());
    foreach ($files as $file)
    {
      $omClass = basename($file, 'MapBuilder.php');
      if (class_exists($omClass) && is_subclass_of($omClass, 'BaseObject'))
      {
        $mapBuilderClass = basename($file, '.php');
        $map = new $mapBuilderClass();
        if (!$map->isBuilt())
        {
          $map->doBuild();
        }
      }
    }
  }

  /**
   * Dumps data to fixture from one or more tables.
   *
   * @param string $directoryOrFile   The directory or file to dump to
   * @param mixed  $tables            The name or names of tables to dump (or all to dump all tables)
   * @param string $connectionName    The connection name (default to propel)
   */
  public function dumpData($directoryOrFile, $tables = 'all', $connectionName = 'propel')
  {
    $dumpData = $this->getData($tables, $connectionName);

    // save to file(s)
    if (!is_dir($directoryOrFile))
    {
      file_put_contents($directoryOrFile, sfYaml::dump($dumpData, 3));
    }
    else
    {
      $i = 0;
      foreach ($tables as $tableName)
      {
        if (!isset($dumpData[$tableName]))
        {
          continue;
        }

        file_put_contents(sprintf("%s/%03d-%s.yml", $directoryOrFile, ++$i, $tableName), sfYaml::dump(array($tableName => $dumpData[$tableName]), 3));
      }
    }
  }

  /**
   * Returns data from one or more tables.
   *
   * @param  mixed  $tables           name or names of tables to dump (or all to dump all tables)
   * @param  string $connectionName   connection name
   *
   * @return array  An array of database data
   */
  public function getData($tables = 'all', $connectionName = 'propel')
  {
    $this->loadMapBuilders();
    $this->con = Propel::getConnection($connectionName);
    $this->dbMap = Propel::getDatabaseMap($connectionName);

    // get tables
    if ('all' === $tables || is_null($tables))
    {
      $tables = array();
      foreach ($this->dbMap->getTables() as $table)
      {
        $tables[] = $table->getPhpName();
      }
    }
    else if (!is_array($tables))
    {
      $tables = array($tables);
    }

    $dumpData = array();

    $tables = $this->fixOrderingOfForeignKeyData($tables);
    foreach ($tables as $tableName)
    {
      $tableMap = $this->dbMap->getTable(constant(constant($tableName.'::PEER').'::TABLE_NAME'));
      $hasParent = false;
      $haveParents = false;
      $fixColumn = null;
      foreach ($tableMap->getColumns() as $column)
      {
        $col = strtolower($column->getColumnName());
        if ($column->isForeignKey())
        {
          $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
          if ($tableName === $relatedTable->getPhpName())
          {
            if ($hasParent)
            {
              $haveParents = true;
            }
            else
            {
              $fixColumn = $column;
              $hasParent = true;
            }
          }
        }
      }

      if ($haveParents)
      {
        // unable to dump tables having multi-recursive references
        continue;
      }

      // get db info
      $resultsSets = array();
      if ($hasParent)
      {
        $resultsSets = $this->fixOrderingOfForeignKeyDataInSameTable($resultsSets, $tableName, $fixColumn);
      }
      else
      {
        $stmt = $this->con->query('SELECT * FROM '.constant(constant($tableName.'::PEER').'::TABLE_NAME'));
        $resultsSets[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }

      foreach ($resultsSets as $rows)
      {
        if(count($rows) > 0 && !isset($dumpData[$tableName]))
        {
          $dumpData[$tableName] = array();

          foreach ($rows as $row)
          {
            $pk = $tableName;
            $values = array();
            $primaryKeys = array();
            $foreignKeys = array();

            foreach ($tableMap->getColumns() as $column)
            {
              $col = strtolower($column->getColumnName());
              $isPrimaryKey = $column->isPrimaryKey();

              if (is_null($row[$col]))
              {
                continue;
              }

              if ($isPrimaryKey)
              {
                $value = $row[$col];
                $pk .= '_'.$value;
                $primaryKeys[$col] = $value;
              }

              if ($column->isForeignKey())
              {
                $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
                if ($isPrimaryKey)
                {
                  $foreignKeys[$col] = $row[$col];
                  $primaryKeys[$col] = $relatedTable->getPhpName().'_'.$row[$col];
                }
                else
                {
                  $values[$col] = $relatedTable->getPhpName().'_'.$row[$col];

                  $values[$col] = strlen($row[$col]) ? $relatedTable->getPhpName().'_'.$row[$col] : '';
                }
              }
              elseif (!$isPrimaryKey || ($isPrimaryKey && !$tableMap->isUseIdGenerator()))
              {
                // We did not want auto incremented primary keys
                $values[$col] = $row[$col];
              }
            }

            if (count($primaryKeys) > 1 || (count($primaryKeys) > 0 && count($foreignKeys) > 0))
            {
              $values = array_merge($primaryKeys, $values);
            }

            $dumpData[$tableName][$pk] = $values;
          }
        }
      }
    }

    return $dumpData;
  }

  /**
   * Fixes the ordering of foreign key data, by outputting data a foreign key depends on before the table with the foreign key.
   *
   * @param array $classes The array with the class names.
   */
  public function fixOrderingOfForeignKeyData($classes)
  {
    // reordering classes to take foreign keys into account
    for ($i = 0, $count = count($classes); $i < $count; $i++)
    {
      $class = $classes[$i];
      $tableMap = $this->dbMap->getTable(constant(constant($class.'::PEER').'::TABLE_NAME'));
      foreach ($tableMap->getColumns() as $column)
      {
        if ($column->isForeignKey())
        {
          $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
          $relatedTablePos = array_search($relatedTable->getPhpName(), $classes);

          // check if relatedTable is after the current table
          if ($relatedTablePos > $i)
          {
            // move related table 1 position before current table
            $classes = array_merge(
              array_slice($classes, 0, $i),
              array($classes[$relatedTablePos]),
              array_slice($classes, $i, $relatedTablePos - $i),
              array_slice($classes, $relatedTablePos + 1)
            );

            // we have moved a table, so let's see if we are done
            return $this->fixOrderingOfForeignKeyData($classes);
          }
        }
      }
    }

    return $classes;
  }

  protected function fixOrderingOfForeignKeyDataInSameTable($resultsSets, $tableName, $column, $in = null)
  {
    $stmt = $this->con->prepare('SELECT * FROM :table WHERE :column :where');
    $stmt->bindValue(':table', constant(constant($tableName.'::PEER').'::TABLE_NAME'));
    $stmt->bindValue(':column', strtolower($column->getColumnName()));
    $stmt->bindValue(':where', is_null($in) ? 'IS NULL' : 'IN ('.$in.')');

    $stmt = $stmt->execute();

    $first = null;
    $in = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
      if(is_null($first))
      {
        $first = $row;
      }

      $in[] = "'".$row[strtolower($column->getRelatedColumnName())]."'";
    }

    if ($in = implode(', ', $in))
    {
      $resultsSets[] = $first;
      $resultsSets = $this->fixOrderingOfForeignKeyDataInSameTable($resultsSets, $tableName, $column, $in);
    }

    return $resultsSets;
  }
}
