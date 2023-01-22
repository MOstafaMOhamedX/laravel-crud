<?php

namespace MOstafaMOhamedX\Crud\Formatters;

use MOstafaMOhamedX\Crud\Generators\BaseGen;

class Formatter extends BaseGen {

    public function fixSoftDelete(?string $str) {
        if (!$this->hasSoftDeletes()) {
            $str = preg_replace('#//@softdelete.*?@endsoftdelete#s', '', $str);
        }

        return $str;
    }
}
