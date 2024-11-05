<?php

class QueryBuilder
{
    private $db;
    private $table;
    private $columns = '';
    private $bindings = [];
    private $where = '';
    private $order = [];
    private $limit;
    private $joins = [];
    private $groupBy;
    private $bindCounter = 0;

    public function __construct(PDO $db, $counterContinues = null)
    {
        $this->db = $db;
        if (!is_null($counterContinues)) {
            $this->bindCounter = $counterContinues;
        }
    }

    public function raw($query, $bindings = null)
    {
        $db = $this->getDB();

        if (is_array($bindings)) {
            return $db->prepare($query)->execute($bindings);
        }

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

    private function formatColumn($column)
    {
        $exp = null;
        if (!is_array($column)) {
            $column = rtrim($column, ',');
        }

        if (is_array($column)) {
            $exp = $column;
        } else if (strpos($column, ',') !== false) {
            $exp = explode(',', $column);
        }

        $newColumns = '';
        if (is_array($exp)) {
            foreach ($exp as $col) {
                if (strpos($col, '.') !== false) {
                    $expCol = explode('.', $col);
                    $newColumns .= '`' . $expCol[0] . '`.';
                    if ($expCol[1] != '*') {
                        $newColumns .= $this->parseAsColumn($expCol[1]);
                    } else {
                        $newColumns .= $expCol[1] . ', ';
                    }
                } else {
                    if ($col != '*') {
                        $newColumns .= $this->parseAsColumn($col);
                    } else {
                        $newColumns .= $col . ', ';
                    }
                }
            }
        } else {
            if ($column != '*') {
                if (strpos($column, '.') !== false) {
                    $expCol = explode('.', $column);
                    $newColumns .= '`' . $expCol[0] . '`.';
                    if ($expCol[1] != '*') {
                        $newColumns .= $this->parseAsColumn($expCol[1]);
                    } else {
                        $newColumns .= $expCol[1] . ', ';
                    }
                } else {
                    $newColumns .= $this->parseAsColumn($column);
                }
            } else {
                $newColumns = $column;
            }
        }

        return rtrim($newColumns, ', ');
    }

    private function parseAsColumn($column)
    {
        $newColumns = '';
        if (strpos($column, ' as ') !== false) {
            $expCol = explode(' as ', $column);
            $newColumns .= '`' . $expCol[0] . '` AS ';
            if ($expCol[1] != '*') {
                $newColumns .= '`' . $expCol[1] . '`, ';
            } else {
                $newColumns .= $expCol[1] . ', ';
            }
        } else if (strpos($column, ' AS ') !== false) {
            $expCol = explode(' AS ', $column);
            $newColumns .= '`' . $expCol[0] . '` AS ';
            if ($expCol[1] != '*') {
                $newColumns .= '`' . $expCol[1] . '`, ';
            } else {
                $newColumns .= $expCol[1] . ', ';
            }
        } else {
            $newColumns .= '`' . $column . '`, ';
        }

        return $newColumns;
    }

    public function table($table)
    {
        $this->table = $this->formatColumn(trim($table));
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
            $columns = $this->formatColumn($columns);
        } else {
            $columns = '*';
        }

        return $db->query("SELECT " . $columns . " FROM " . $table)->fetchAll();
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
        if ($query) {
            return true;
        }
        return false;
    }

    public function whereRaw($condition)
    {
        $this->where .= trim($condition, ' ') . ' ';
        return $this;
    }

    public function where($column, $operator = null, $value = null)
    {
        if (is_callable($column)) {
            $subQuery = new static($this->getDB(), $this->bindCounter);
            $column($subQuery);

            $this->where .= ' AND (' . $subQuery->where;
            $this->bindings = array_merge($this->bindings, $subQuery->bindings);
            $this->bindCounter = $subQuery->bindCounter;

            $this->where = ltrim($this->where, ' AND ');
            $this->where .= ') ';
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $bindKey = ':wa' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $this->where .= 'AND ' . $this->formatColumn($column) . ' ' . $operator . ' ' . $bindKey . ' ';
            $this->bindings[$bindKey] = $value;
            $this->where = ltrim($this->where, 'AND ');
        }

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_callable($column)) {
            $subQuery = new static($this->getDB(), $this->bindCounter);
            $column($subQuery);

            $this->where .= ' OR (' . $subQuery->where;
            $this->bindings = array_merge($this->bindings, $subQuery->bindings);
            $this->bindCounter = $subQuery->bindCounter;

            $this->where = ltrim($this->where, ' OR ');
            $this->where .= ') ';
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $bindKey = ':wo' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $this->where .= 'OR ' . $this->formatColumn($column) . ' ' . $operator . ' ' . $bindKey . ' ';
            $this->bindings[$bindKey] = $value;
            $this->where = ltrim($this->where, 'OR ');
        }

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

        $this->where .= 'AND ' . $this->formatColumn($column) . ' IN (' . $clauseText . ') ';
        return $this;
    }

    public function orWhereIn($column, $values)
    {
        $clauseText = '';
        foreach ($values as $index => $item) {
            $bindKey = ':wi' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $clauseText .= $bindKey . ',';
            $this->bindings[$bindKey] = $item;
        }

        $clauseText = rtrim($clauseText, ',');

        $this->where .= 'OR ' . $this->formatColumn($column) . ' IN (' . $clauseText . ') ';
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

        $this->where .= 'AND ' . $this->formatColumn($column) . ' NOT IN (' . $clauseText . ') ';
        return $this;
    }

    public function orWhereNotIn($column, $values)
    {
        $clauseText = '';
        foreach ($values as $index => $item) {
            $bindKey = ':wni' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
            $clauseText .= $bindKey . ',';
            $this->bindings[$bindKey] = $item;
        }

        $clauseText = rtrim($clauseText, ',');

        $this->where .= 'OR ' . $this->formatColumn($column) . ' NOT IN (' . $clauseText . ') ';
        return $this;
    }

    public function whereBetween($column, $min, $max)
    {
        $bindKeyStart = ':wbs' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
        $bindKeyEnd = ':wbe' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where .= 'AND ' . $this->formatColumn($column) . ' BETWEEN ' . $bindKeyStart . ' AND ' . $bindKeyEnd . ' ';
        $this->bindings[$bindKeyStart] = $min;
        $this->bindings[$bindKeyEnd] = $max;
        return $this;
    }

    public function orWhereBetween($column, $min, $max)
    {
        $bindKeyStart = ':wbs' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
        $bindKeyEnd = ':wbe' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where .= 'OR ' . $this->formatColumn($column) . ' BETWEEN ' . $bindKeyStart . ' AND ' . $bindKeyEnd . ' ';
        $this->bindings[$bindKeyStart] = $min;
        $this->bindings[$bindKeyEnd] = $max;
        return $this;
    }

    public function isNull($column)
    {
        $this->where .= 'AND ' . $this->formatColumn($column) . ' IS NULL ';
        return $this;
    }

    public function isNotNull($column)
    {
        $this->where .= 'AND ' . $this->formatColumn($column) . ' IS NOT NULL ';
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->order[] = $this->formatColumn($column) . ' ' . strtoupper($direction);
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
        $this->joins[] = $join_type . ' ' . $this->formatColumn($reference_table) . ' ON ' . $table . '.' . $this->formatColumn($local_column) . ' = ' . $this->formatColumn($reference_table) . '.' . $this->formatColumn($reference_column);
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
        $this->columns .= $this->formatColumn($columns) . ',';

        return $this;
    }

    public function selectSum($column, $as = null)
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'SUM(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function selectDistinct($column, $as = null)
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'DISTINCT(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function selectCount($column, $as = null)
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'COUNT(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function groupBy($columns)
    {
        $this->groupBy = $this->formatColumn($columns);

        return $this;
    }

    /**
     * @param string $buildType = 'all','count','single','delete','update', 'one'
     */
    private function buildQuery($buildType = 'all', $baseQueryOverride = null)
    {
        $table = $this->getTable();

        if ($buildType == 'count') {
            $columnsText = 'COUNT(*) as aggregate';
        } else {
            if ($this->columns != '') {
                $columnsText = rtrim($this->columns, ',');
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

        if (!empty($this->where)) {
            $whereText = trim($this->where, 'AND ');
            $whereText = trim($whereText, 'OR ');
            $sqlText .= ' WHERE ' . $whereText;
        }

        if (!empty($this->groupBy)) {
            $sqlText .= ' GROUP BY ' . $this->groupBy;
        }

        if (!empty($this->order) && count($this->order)) {
            $sqlText .= ' ORDER BY ' . implode(', ', $this->order);
        }

        $dontAddLimitFor = ['count', 'single'];
        if (!in_array($buildType, $dontAddLimitFor) && !empty($this->limit)) {
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
        $this->where = '';
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

        return $query->rowCount();
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

    public function one()
    {
        $db = $this->getDB();
        $queryText = $this->buildQuery('one');

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
