<?php

class QueryBuilder
{
    private $db;
    private $table;
    private $columns;
    private $bindings = [];
    private $where = [];
    private $orWhere = [];
    private $order = [];
    private $limit;
    private $joins = [];
    private $groupBy;
    private $bindCounter = 0;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function raw($query)
    {
        $db = $this->getDB();

        return $db->query($query);
    }

    public function execRaw($query)
    {
        $db = $this->getDB();

        return $db->exec($query);
    }

    private function getDB()
    {
        return $this->db;
    }

    private function getNewCounter()
    {
        $this->bindCounter += 1;
        return $this->bindCounter;
    }

    public function table($table)
    {
        $this->table = trim($table);
        return $this;
    }

    private function getTable()
    {
        return $this->table;
    }

    public function all($columns = null)
    {
        $db = $this->getDB();
        $table = $this->getTable();

        if (!empty($columns)) {
            if (is_array($columns)) {
                $columns = implode(',', $columns);
            } else {
                $columns = trim($columns, ',');
            }
        } else {
            $columns = '*';
        }

        $results = $db->query("SELECT " . $columns . " FROM " . $table);
        $results = $results->fetchAll();
        return $results;
    }

    public function create($params = [])
    {
        $db = $this->getDB();
        $table = $this->getTable();

        $columns = implode(',', array_keys($params));
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $values = array_values($params);

        $query = $db->prepare("INSERT INTO " . $table . "(" . $columns . ") VALUES(" . $placeholders . ")");
        $query->execute($values);

        return $db->lastInsertId();
    }

    public function update($params = [])
    {
        $db = $this->getDB();
        $table = $this->getTable();

        $cols = '';
        foreach ($params as $colName => $val) {
            $bindKey = ':upt' . $this->getNewCounter() . '_' . str_replace('.', '_', $colName);
            $cols .= $colName . ' = ' . $bindKey . ', ';
            $this->bindings[$bindKey] = $val;
        }

        $cols = rtrim($cols, ', ');

        $baseQuery = "UPDATE " . $table . " SET " . $cols;

        $queryText = $this->buildQuery('update', $baseQuery);

        $query = $db->prepare($queryText);
        $query->execute($this->bindings);

        $this->resetDefaults();
        if($query){
            return true;
        }
        return false;
    }

    public function whereRaw($condition)
    {
        $this->where[] = $condition;
        return $this;
    }

    public function where($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $bindKey = ':wa' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where[] = $column . ' ' . $operator . ' ' . $bindKey;
        $this->bindings[$bindKey] = $value;

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $bindKey = ':wo' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->orWhere[] = $column . ' ' . $operator . ' ' . $bindKey;
        $this->bindings[$bindKey] = $value;

        return $this;
    }

    public function whereIn($column, $values)
    {
        $clauseText = '';
        foreach ($values as $index => $item) {
            $bindKey = ':wi' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $clauseText .= $bindKey . ',';
            $this->bindings[$bindKey] = $item;
        }

        $clauseText = rtrim($clauseText, ',');

        $this->where[] = $column . ' IN (' . $clauseText . ')';
        return $this;
    }

    public function whereNotIn($column, $values)
    {
        $clauseText = '';
        foreach ($values as $index => $item) {
            $bindKey = ':wni' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $clauseText .= $bindKey . ',';
            $this->bindings[$bindKey] = $item;
        }

        $clauseText = rtrim($clauseText, ',');

        $this->where[] = $column . ' NOT IN (' . $clauseText . ')';
        return $this;
    }

    public function whereBetween($column, $min, $max)
    {
        $bindKeyStart = ':wbs' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
        $bindKeyEnd = ':wbe' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where[] = $column . ' BETWEEN ' . $bindKeyStart . ' AND ' . $bindKeyEnd;
        $this->bindings[$bindKeyStart] = $min;
        $this->bindings[$bindKeyEnd] = $max;
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->order[] = $column . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit($limit, $take = null)
    {
        if (!empty($take)) {
            $limit .= ', ' . $take;
        }

        $this->limit = $limit;
        return $this;
    }

    private function addJoin($reference_table, $reference_column, $local_column, $join_type = 'JOIN')
    {
        $table = $this->getTable();
        $this->joins[] = $join_type . ' ' . $reference_table . ' ON ' . $reference_table . '.' . $reference_column . ' = ' . $table . '.' . $local_column;
        return $this;
    }

    public function join($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'JOIN');
    }

    public function innerJoin($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'INNER JOIN');
    }

    public function leftJoin($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'LEFT JOIN');
    }

    public function rightJoin($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'RIGHT JOIN');
    }

    public function crossJoin($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'CROSS JOIN');
    }

    public function outerJoin($reference_table, $reference_column, $local_column)
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'OUTER JOIN');
    }

    public function select($columns)
    {
        if (is_array($columns)) {
            $columns = implode(',', $columns);
        } else {
            $columns = trim($columns, ',');
        }

        $this->columns = $columns;

        return $this;
    }

    public function groupBy($columns)
    {
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        } else {
            $columns = trim($columns, ',');
        }

        $this->groupBy = $columns;

        return $this;
    }

    /**
     * @param string $buildType = 'all','count','single','delete','update'
     */
    private function buildQuery($buildType = 'all', $baseQueryOverride = null)
    {
        $table = $this->getTable();

        if ($buildType == 'count') {
            $columnsText = 'COUNT(*) as aggregate';
        } else {
            if ($this->columns != '') {
                $columnsText = $this->columns;
            } else {
                $columnsText = '*';
            }
        }

        $sqlText = '';
        $baseQuery = "SELECT " . $columnsText . " FROM " . $table;
        if ($buildType == 'delete') {
            $baseQuery = "DELETE FROM " . $table;
        } else if ($buildType == 'update') {
            $baseQuery = $baseQueryOverride;
        }


        if (!empty($this->joins) && count($this->joins)) {
            $baseQuery .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->where) && count($this->where)) {
            $sqlText .= ' WHERE (' . implode(' AND ', $this->where) . ')';
        }

        if (!empty($this->orWhere) && count($this->orWhere)) {
            if ($sqlText != '') {
                $sqlText .= ' AND ';
            } else {
                $sqlText .= ' WHERE ';
            }

            $sqlText .= '(' . implode(' OR ', $this->orWhere) . ')';
        }

        if (!empty($this->groupBy)) {
            $sqlText .= ' GROUP BY ' . $this->groupBy;
        }

        if (!empty($this->order) && count($this->order)) {
            $sqlText .= ' ORDER BY ' . implode(', ', $this->order);
        }

        $dontAddLimitFor = ['count','single'];
        if (!in_array($buildType,$dontAddLimitFor) && !empty($this->limit)) {
            $sqlText .= ' LIMIT ' . $this->limit;
        }

        if ($buildType == 'single') {
            $sqlText .= ' LIMIT 1';
        }

        return $baseQuery . $sqlText;
    }

    public function toRawSql()
    {
        $rawSql = $this->buildQuery('all');
        $bindings = $this->bindings;
        if (!empty($bindings) && count($bindings)) {
            $newBindings = [];
            foreach ($bindings as $bindingKey => $bindingValue) {
                $replaceText = "'" . $bindingValue . "'";
                if (is_numeric($bindingValue)) {
                    $replaceText = $bindingValue;
                }
                $newBindings[$bindingKey] = $replaceText;
            }

            $keys = array_keys($newBindings);
            $vals = array_values($newBindings);
            $rawSql = str_replace($keys, $vals, $rawSql);
        }

        $this->resetDefaults();

        return $rawSql;
    }

    private function resetDefaults()
    {
        $this->table = '';
        $this->columns = '';
        $this->bindings = [];
        $this->where = [];
        $this->orWhere = [];
        $this->order = [];
        $this->limit = '';
        $this->joins = [];
        $this->groupBy = '';
        $this->bindCounter = 0;
    }

    public function delete()
    {
        $db = $this->getDB();
        $queryText = $this->buildQuery('delete');

        $query = $db->prepare($queryText);
        $query->execute($this->bindings);

        $this->resetDefaults();

        return $query->fetch();
    }

    public function count()
    {
        $db = $this->getDB();
        $queryText = $this->buildQuery('count');

        $query = $db->prepare($queryText);
        $query->execute($this->bindings);

        $this->resetDefaults();

        return $query->fetchColumn();
    }

    public function first()
    {
        $db = $this->getDB();
        $queryText = $this->buildQuery('single');

        $query = $db->prepare($queryText);
        $query->execute($this->bindings);

        $this->resetDefaults();

        return $query->fetch();
    }

    public function get()
    {
        $db = $this->getDB();
        $queryText = $this->buildQuery('all');

        $query = $db->prepare($queryText);
        $query->execute($this->bindings);

        $this->resetDefaults();

        return $query->fetchAll();
    }
}
