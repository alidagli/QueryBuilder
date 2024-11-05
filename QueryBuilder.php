<?php

class QueryBuilder
{
    private PDO $db;
    private string $table;
    private string $columns = '';
    private array $bindings = [];
    private string $where = '';
    private array $order = [];
    private string $limit;
    private array $joins = [];
    private string $groupBy;
    private int $bindCounter = 0;

    public function __construct(PDO $db, int $counterContinues = null)
    {
        $this->db = $db;
        if (!is_null($counterContinues)) {
            $this->bindCounter = $counterContinues;
        }
    }

    public function raw(string $query, array $bindings = null)
    {
        $db = $this->getDB();

        if (is_array($bindings)) {
            return $db->prepare($query)->execute($bindings);
        }

        return $db->query($query);
    }

    public function execRaw(string $query)
    {
        $db = $this->getDB();

        return $db->exec($query);
    }

    private function getDB(): PDO
    {
        return $this->db;
    }

    private function getNewCounter(): int
    {
        $this->bindCounter += 1;
        return $this->bindCounter;
    }

    private function formatColumn(string|array $column): string
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

    private function parseAsColumn(string $column): string
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

    public function table(string $table): QueryBuilder
    {
        $this->table = $this->formatColumn(trim($table));
        return $this;
    }

    private function getTable(): string
    {
        return $this->table;
    }

    public function all(string|array $columns = null)
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

    public function create(array $params = [])
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

    public function update(array $params = [])
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

    public function whereRaw(string $condition): QueryBuilder
    {
        $this->where .= trim($condition, ' ') . ' ';
        return $this;
    }

    /**
     * @param string $type = 'and','andnot','or','ornot','in', 'orin', 'notin', 'ornotin'
     */
    private function generateWhereSub($type, $column, $subQuery): QueryBuilder
    {
        $operator = '=';
        $condition = 'AND';

        if ($type == 'and') {
            $condition = 'AND';
            $operator = '=';
        } else if ($type == 'andnot') {
            $condition = 'AND';
            $operator = '!=';
        } else if ($type == 'or') {
            $condition = 'OR';
            $operator = '=';
        } else if ($type == 'ornot') {
            $condition = 'OR';
            $operator = '!=';
        } else if ($type == 'in') {
            $condition = 'AND';
            $operator = 'IN';
        } else if ($type == 'orin') {
            $condition = 'OR';
            $operator = 'IN';
        } else if ($type == 'notin') {
            $condition = 'AND';
            $operator = 'NOT IN';
        } else if ($type == 'ornotin') {
            $condition = 'OR';
            $operator = 'NOT IN';
        }


        if (is_callable($subQuery)) {
            $subInstance = new static($this->getDB(), $this->bindCounter);
            $subQuery($subInstance);

            $this->where .=  ' ' . $condition . ' ' . $this->formatColumn($column) . ' ' . $operator . ' (' . $subInstance->toSubSql() . ') ';
            $this->bindings = array_merge($this->bindings, $subInstance->bindings);
            $this->bindCounter = $subInstance->bindCounter;
        } else {
            /** raw query */
            $this->where .= ' ' . $condition . ' ' . $this->formatColumn($column) . ' ' . $operator . ' (' . $subQuery . ') ';
        }

        return $this;
    }

    public function whereSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('and', $column, $subQuery);
    }

    public function whereNotSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('andnot', $column, $subQuery);
    }

    public function whereOrSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('or', $column, $subQuery);
    }

    public function whereOrNotSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('ornot', $column, $subQuery);
    }

    public function whereInSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('in', $column, $subQuery);
    }

    public function whereOrInSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('orin', $column, $subQuery);
    }

    public function whereNotInSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('notin', $column, $subQuery);
    }

    public function whereOrNotInSub($column, $subQuery): QueryBuilder
    {
        return $this->generateWhereSub('ornotin', $column, $subQuery);
    }

    public function where($column, $operator = null, $value = null): QueryBuilder
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

    public function orWhere($column, $operator = null, $value = null): QueryBuilder
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

    public function whereIn(string $column, array $values): QueryBuilder
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

    public function orWhereIn(string $column, array $values): QueryBuilder
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

    public function whereNotIn(string $column, array $values): QueryBuilder
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

    public function orWhereNotIn(string $column, array $values): QueryBuilder
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

    public function whereBetween(string $column, string|int $min, string|int $max): QueryBuilder
    {
        $bindKeyStart = ':wbs' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
        $bindKeyEnd = ':wbe' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where .= 'AND ' . $this->formatColumn($column) . ' BETWEEN ' . $bindKeyStart . ' AND ' . $bindKeyEnd . ' ';
        $this->bindings[$bindKeyStart] = $min;
        $this->bindings[$bindKeyEnd] = $max;
        return $this;
    }

    public function orWhereBetween(string $column, string|int $min, string|int $max): QueryBuilder
    {
        $bindKeyStart = ':wbs' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);
        $bindKeyEnd = ':wbe' . $this->getNewCounter() . '_' . str_replace('.', '_', $column);

        $this->where .= 'OR ' . $this->formatColumn($column) . ' BETWEEN ' . $bindKeyStart . ' AND ' . $bindKeyEnd . ' ';
        $this->bindings[$bindKeyStart] = $min;
        $this->bindings[$bindKeyEnd] = $max;
        return $this;
    }

    public function isNull(string $column): QueryBuilder
    {
        $this->where .= 'AND ' . $this->formatColumn($column) . ' IS NULL ';
        return $this;
    }

    public function isNotNull(string $column): QueryBuilder
    {
        $this->where .= 'AND ' . $this->formatColumn($column) . ' IS NOT NULL ';
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): QueryBuilder
    {
        $this->order[] = $this->formatColumn($column) . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit, int $take = null): QueryBuilder
    {
        if (!empty($take)) {
            $limit .= ', ' . $take;
        }

        $this->limit = $limit;
        return $this;
    }

    private function addJoin(string $reference_table, string $reference_column, string $local_column, string $join_type = 'JOIN'): QueryBuilder
    {
        $table = $this->getTable();
        $this->joins[] = $join_type . ' ' . $this->formatColumn($reference_table) . ' ON ' . $table . '.' . $this->formatColumn($local_column) . ' = ' . $this->formatColumn($reference_table) . '.' . $this->formatColumn($reference_column);
        return $this;
    }

    public function join(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'JOIN');
    }

    public function innerJoin(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'INNER JOIN');
    }

    public function leftJoin(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'LEFT JOIN');
    }

    public function rightJoin(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'RIGHT JOIN');
    }

    public function crossJoin(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'CROSS JOIN');
    }

    public function outerJoin(string $reference_table, string $reference_column, string $local_column): QueryBuilder
    {
        return $this->addJoin($reference_table, $reference_column, $local_column, 'OUTER JOIN');
    }

    public function select(string|array $columns, ?string $as = null): QueryBuilder
    {
        $asText = '';
        if (!is_array($columns) && $as !== null) {
            $asText = ' AS ' . $this->formatColumn($as);
        }

        $this->columns .= $this->formatColumn($columns) . $asText . ',';

        return $this;
    }

    public function selectSum(string $column, ?string $as = null): QueryBuilder
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'SUM(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function selectDistinct(string $column, ?string $as = null): QueryBuilder
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'DISTINCT(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function selectCount(string $column, ?string $as = null): QueryBuilder
    {
        $columnAs = null;
        if (!is_null($as)) {
            $columnAs = ' AS ' . $this->formatColumn($as);
        }
        $this->columns .= 'COUNT(' . $this->formatColumn($column) . ')' . $columnAs . ',';
        return $this;
    }

    public function groupBy(string|array $columns): QueryBuilder
    {
        $this->groupBy = $this->formatColumn($columns);

        return $this;
    }

    /**
     * @param string $buildType = 'all','count','single','delete','update', 'one'
     */
    private function buildQuery(string $buildType = 'all', string $baseQueryOverride = null): string
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

    public function toSubSql()
    {
        return $this->buildQuery('all');
    }

    public function toRawSql($returnType = null): string
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

    private function resetDefaults(): void
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

    public function delete(): int
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
