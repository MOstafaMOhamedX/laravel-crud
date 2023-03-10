<?php

namespace MOstafaMOhamedX\Crud\Generators;

use MOstafaMOhamedX\Crud\Utils\NameUtils;
use MOstafaMOhamedX\Crud\Utils\SchemaUtils;

class ControllerGen extends BaseGen {
    public function __construct(public array $tables, public array $aliases = [], public bool $singleForm = FALSE) {
        parent::__construct($tables, $aliases);
    }

    public function getControllerName() {
        return NameUtils::getControllerName($this->tables);
    }

    public function getControllerAllArgs() {
        return $this->getControllerArgs($this->tables);
    }

    public function getControllerArgs(array $tables) {
        $value = join(', ', array_map(fn($table) => sprintf('%s $%s', NameUtils::getModelName((array) $table), NameUtils::getVariableName($table)), $tables));
        return !empty($value) ? "$value," : "";
    }

    public function getControllerParentArgs() {
        return $this->getControllerArgs($this->parentTables());
    }

    public function getFindById() {
        return sprintf('$%s = %s::withTrashed()%s->find($%s_id);', $this->getVarName(), $this->getMainModelName(),
            $this->hasUserId() ? "->where('user_id', auth()->id())" : "", $this->getMainVarName());
    }

    public function getQuery() {
        if ($this->hasParentTable()) {
            $code[] = sprintf('$%s = $%s->%s();', $this->getVarNamePlural(), $this->getParentVarName(), $this->mainTable());
        } else {
            $code[] = sprintf('$%s = %s::query();', $this->getVarNamePlural(), $this->getMainModelName());
        }

        foreach (array_slice($this->tables, -2) as $table) {
            $realTable = $this->getTableNameFromAlias($table);
            if ($userIdField = SchemaUtils::getUserIdField($realTable)) {
                $code[] = sprintf("\t\t\$%s->where('%s', auth()->id());", $realTable === $this->mainTableReal() ? NameUtils::getVariableNamePlural($table) : NameUtils::getVariableName($table), $userIdField);
            }
        }

        if ($this->hasSoftDeletes()) {
            $code[] = sprintf("\n\t\tif (!!\$request->trashed) {\n\t\t\t\$%s->withTrashed();\n\t\t}", $this->getVarNamePlural());
        }

        return join("\n", $code);
    }

    public function getSearch() {
        return sprintf("if(!empty(\$request->search)) {\n\t\t\t\$%s->where('%s', 'like', '%%' . \$request->search . '%%');\n\t\t}", $this->getVarNamePlural(), SchemaUtils::firstHumanReadableField($this->mainTableReal(), 'id') ?: 'id');
    }

    public function getPager() {
        return sprintf('$%s = $%s->paginate(10);', $this->getVarNamePlural(), $this->getVarNamePlural());
    }

    public function getIndexVars() {
        return $this->getVars($this->parentTables(), (array) $this->getVarNamePlural());
    }

    public function getAllVars() {
        return $this->getVars($this->tables);
    }

    public function getParentVars() {
        return $this->getVars($this->parentTables());
    }

    public function getCreateVar() {
        return $this->singleForm ? sprintf('$%s = new %s();', $this->getVarName(), $this->getMainModelName()) : '';
    }

    public function getCreateVars() {
        $extraVars = (array) array_map(fn($field) => $field['related_table'], (array) $this->getExternallyRelatedFields());
        $createVar = $this->singleForm ? [$this->getVarName()] : [];
        $result = $this->getVars($this->parentTables(), array_merge($extraVars, $createVar));

        return $result;
    }

    public function getEditVars() {
        return $this->getVars($this->tables, (array) array_map(fn($field) => $field['related_table'], (array) $this->getExternallyRelatedFields()));
    }

    public function getCreateView() {
        return $this->singleForm ? 'create-edit' : 'create';
    }

    public function getEditView() {
        return $this->singleForm ? 'create-edit' : 'edit';
    }

    public function getWith() {
        foreach ($this->getExternallyRelatedFields() as $field) {
            $code[] = sprintf('$%s->with(\'%s\');', $this->getVarNamePlural(), $field['relation']);
        }

        return join("\n\t\t", $code ?? []);
    }

    public function getSelects() {
        foreach ($this->getExternallyRelatedFields() as $field) {
            $realTable = $this->getTableNameFromAlias($field['related_table']);
            if (SchemaUtils::getUserIdField($realTable)) {
                $code[] = sprintf("\$%s = \App\Models\%s::where('%s', auth()->id())->get();", NameUtils::getVariableNamePlural($realTable), NameUtils::getModelName($realTable), SchemaUtils::getUserIdField($realTable));
            } else {
                $code[] = sprintf("\$%s = \App\Models\%s::all();", NameUtils::getVariableNamePlural($realTable), NameUtils::getModelName($realTable));
            }
        }

        return join("\n\t\t", $code ?? []);
    }

    public function getValidations(bool $edit) {
        foreach ($this->getFillableFields() as $field) {
            if (preg_match('/boolean|timestamp/', $field['type'])) continue;

            $requirements = [];

            if (!$field['nullable']) {
                $requirements[] = 'required';
            }

            if (preg_match('/email$/i', $field['id'])) {
                $requirements[] = 'email';
            } elseif (preg_match('/(url)/i', $field['id'])) {
                $requirements[] = 'url';
            } elseif (preg_match('/(integer|float|double|decimal)/i', $field['type'])) {
                $requirements[] = 'numeric';
            } elseif (preg_match('/(date|datetime)/i', $field['type'])) {
                $requirements[] = 'date';
            }

            if (!empty($field['unique'])) {
                $unique = "unique:{$this->mainTableReal()},{$field['id']}";
                if ($edit) {
                    $unique .= ",\${$this->getVarName()}->id";
                }

                $requirements[] = $unique;
            }

            $validations[$field['id']] = join('|', $requirements);
        }

        if (!empty($validations)) {
            $keyValues = array_map(fn($key, $value) => sprintf('"%s" => "%s"', $key, $value), array_keys($validations), $validations);
            return sprintf("[%s]", join(", ", $keyValues));
        } else {
            return '[]';
        }
    }

    public function getStore($edit) {
        foreach (SchemaUtils::getTableFields($this->mainTableReal()) as $field) {
            if ($field['id'] === 'user_id') {
                if (!$edit) $fills[] = sprintf("\$%s->user_id = auth()->id();", $this->getVarName());
            } else if (in_array($field['related_table'] ?? '', $this->realTables())) {
                if (!$edit) {
                    $fills[] = sprintf("\$%s->%s = \$%s->id;", $this->getVarName(), $field['id'], NameUtils::getVariableName($this->getAliasFromTableName($field['related_table'])));
                }
            } else {
                $bool = preg_match('/bool/', $field['type']) ? '!!' : '';
                $fills[] = sprintf("\$%s->%s = %s\$request->%s;", $this->getVarName(), $field['id'], $bool, $field['id']);
            }
        }

        return join("\n\t\t", $fills ?? []);
    }
}
