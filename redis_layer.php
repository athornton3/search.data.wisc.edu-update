<?php

include_once 'specification.php';
include_once 'data_definition.php';
include_once 'relation.php';
include_once 'query_builder.php';
include_once 'datalayer.php';

require "predis/autoload.php";
Predis\Autoloader::register();


function get_redis_specifications($search_input, $sort_by, $functional_area) {
    $functional_areas_table = [
        "all" => "",
        "uwmadison" => "University of Wisconsin - Madison",
        "cirriculuminstruction" => "Curriculum & Instruction",
        "facultystaff" => "Faculty & Staff",
        "finance" => "Finance",
        "research" => "Research",
        "students" => "Students"
    ];
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        if (!$redis->exists('all_specifications')) {
            // Load all specifications into Redis?
        }

        $results = json_decode($redis->get('all_specifications'));

        // Default Landing
        // Still need to check for functional areas
        if (strcmp('', $search_input) == 0 && strcmp('relevance', $sort_by) == 0) {
            $filtered_array = array();
            foreach ($results as $result) {
                //if (contains_area($result->functional_areas, $functional_areas_table[$functional_area])) {
                if (contains_area($result->functional_areas, $functional_area)) {
                    array_push($filtered_array, $result);
                }
            }
            return $filtered_array;
        }
        else {
            if (strcmp('dates', $sort_by) == 0) {
                $filtered_array = array();
                foreach ($results as $result) {
                    if (contains_area($result->functional_areas, $functional_areas_table[$functional_area])) {
                        array_push($filtered_array, $result);
                    }
                }
                usort($filtered_array, "cmp_date");
                return $filtered_array;
            }
            if (strcmp('', $search_input) != 0 && strcmp('relevance', $sort_by) == 0) {
                $filtered_array = array();

                // Finished my nightly debt for the [damn] 'Man' 
        
                $input_array = explode(' ', $search_input);
                foreach ($results as $result) {
                    $specification_array = explode(' ', $result->specification_name);
                    $specification_word_count = count($specification_array);
                    $word_occurrences = 0;

                    foreach ($input_array as $key_word) {
                        foreach ($specification_array as $compare_word) {
                            $distance = levenshtein(strtolower($key_word), strtolower($compare_word));
                            if ($distance <= 1 && $distance >= -1) {
                                $word_occurrences++;
                            }
                        }
                    }
                    if ($word_occurrences > 0 && contains_area($result->functional_areas, $functional_areas_table[$functional_area])) {
                        $ratio = $word_occurrences / (float) $specification_word_count;
                        $result->ratio = $ratio;
                        array_push($filtered_array, $result);
                    }
                }
                usort($filtered_array, "cmp_ratio");
                return $filtered_array;
            }
        }
    }
    catch (Exception $e) {
        return $e->getMessage();
    }
}

function get_redis_definitions($search_input, $sort_By, $functional_area) {
    $functional_areas_table = [
        "all" => "",
        "uwmadison" => "University of Wisconsin - Madison",
        "cirriculuminstruction" => "Curriculum & Instruction",
        "facultystaff" => "Faculty & Staff",
        "finance" => "Finance",
        "research" => "Research",
        "students" => "Students"
    ];
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        if (!$redis->exists('all_definitions')) {
            // Load all specifications into Redis?
        }

        $results = json_decode($redis->get('all_definitions'));

        // Default Landing
        // Still need to check for functional areas
        if (strcmp('', $search_input) == 0) {
            $filtered_array = array();
            foreach ($results as $result) {
                if (contains_area($result->functional_areas, $functional_areas_table[$functional_area])) {
                    array_push($filtered_array, $result);
                }
            }
            return $filtered_array;
        }
        else {
            $filtered_array = array();

            //$input_array = explode(' ', $search_input);
            $input_array = preg_split("/[\s,\\/]+/", $search_input);
            foreach ($results as $result) {
                //$definition_array = explode(' ', $result->definition_name);
                $definition_array = preg_split("/[\s,\\/]+/", $result->definition_name);
                $definition_word_count = count($definition_array);
                $word_occurrences = 0;

                foreach ($input_array as $key_word) {
                    foreach ($definition_array as $compare_word) {
                        $distance = levenshtein(strtolower($key_word), strtolower($compare_word));
                        if ($distance <= 1 && $distance >= -1) {
                            $word_occurrences++;
                        }
                    }
                }
                if ($word_occurrences > 0 && contains_area($result->functional_areas, $functional_areas_table[$functional_area])) {
                    $ratio = $word_occurrences / (float) $definition_word_count;
                    $result->ratio = $ratio;
                    array_push($filtered_array, $result);
                }
            }
            usort($filtered_array, "cmp_ratio");
            return $filtered_array;
        }
    }
    catch (Exception $e) {
        return $e->getMessage();
    }
}

function get_redis_relations($definition_id) {

}

function get_redis_specification_functional_areas() {
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        if (!$redis->exists('all_specification_functional_areas')) {
        }

        $results = json_decode($redis->get('all_specification_functional_areas'));
        return $results;
    }
    catch (Exception $e) {
        return $e->getMessage();
    }
}

function cmp_ratio($a, $b) {
    $a_ratio = $a->ratio;
    $b_ratio = $b->ratio;
    if ($a_ratio == $b_ratio) {
        return 0;
    }
    return ($a_ratio < $b_ratio) ? 1 : -1;
}

function cmp_date($a, $b) {
    $a_date = strtotime($a->last_revised);
    $b_date = strtotime($b->last_revised);
    return ($a_date > $b_date ? -1 : 1);
}

function contains_area($functional_areas, $area) {
    if (strcmp("all", $area) == 0) {
        return true;
    }
    $pos = strpos($functional_areas, $area);
    if ($pos === false) {
        return false;
    }
    return true;
}

function specifications_set() {
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        return $redis->exists('all_specifications');
    }
    catch (Exception $e) {
        return false;
    }
}

function definitions_set() {
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        return $redis->exists('all_definitions');
    }
    catch (Exception $e) {
        return false;
    }
}

function relations_set() {
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        return $redis->exists('all_relations');
    }
    catch (Exception $e) {
        return false;
    }
}

function functional_areas_set() {
    try {
        $redis = new Predis\Client(array(
            'host' => 'localhost',
            'port' => 26379
        ));
        return $redis->exists('functional_areas');
    }
    catch (Exception $e) {
        return false;
    }
}
?>
