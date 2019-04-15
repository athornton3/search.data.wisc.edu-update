<?php

class QueryBuilder {
    function __construct($search_input, $type, $sort_by, $functional_area, $access_restrictions = '') {
        $this->search_input = $search_input;
        $this->data_type = $type;
        $this->sort_by = $sort_by;
        $this->functional_area = $functional_area;
	$this->access_restrictions = $access_restrictions;

        $this->functional_areas_table = array();
        $this->functional_areas_table['all'] = '';
        $this->functional_areas_table['uwmadison'] = "University of Wisconsin - Madison";
        $this->functional_areas_table['cirriculuminstruction'] = "Curriculum & Instruction";
        $this->functional_areas_table['facultystaff'] = "Faculty & Staff";
        $this->functional_areas_table['finance'] = "Finance";
        $this->functional_areas_table['research'] = "Research";
        $this->functional_areas_table['students'] = "Students";
    }

    /*
     * Builds and returns query based on object properties
     */
    function build_query() {
        // default: report lookup
        $query = $this->query_specifications($this->search_input, $this->sort_by, $this->functional_area, $this->access_restrictions);

        if (0 == strcmp($this->data_type, 'dashboardsReports')) {
            $query = $this->query_specifications($this->search_input, $this->sort_by, $this->functional_area, $this->access_restrictions);
        }
        else if (0 == strcmp($this->data_type, 'dataDefinitions')) {
            $query = $this->query_definitions($this->search_input, $this->sort_by, $this->functional_area);
        }

        return $query;
    }

    // Name
    // Type
    // Description
    // Functional Areas
    // Related Dashboards/Reports
    function query_specifications($search_input, $sort_by, $functional_area, $access_restrictions) {
        $column_like_array = array();
        $input = explode(" ", $search_input);
        usort($input, 'sort');

        foreach ($input as $input_word) {
            array_push($column_like_array, " (sv.specification_name LIKE '%$input_word%') ");
        }

        //$functional_resolved = $this->functional_areas_table[$functional_area];
        $functional_resolved = $functional_area;

        $order_by = "";
        if (strcmp($sort_by, "relevance") == 0) {
            $order_by = "ratio";
        }
        else if (strcmp($sort_by, "dates") == 0) {
            $order_by = "- last_revised";
        }

	if (0 == strcmp("none", $access_restrictions)) {
	   $access_restrictions = '';
	}

        return "
       SELECT *, count_words / (ratio_words * 1.0) AS ratio FROM 
        (SELECT sv.specification_id, sv.specification_name, sv.specification_type, sv.description, sv.additional_details, sv.functional_areas, sva.attribute_4_name, sva.attribute_4_value, sv.version_create_date AS last_revised, (" . join(" + ", $column_like_array) . ") AS count_words, (LENGTH(sv.specification_name) + 1 - LENGTH(REPLACE(sv.specification_name, ' ', ''))) AS ratio_words, sva.version_id as specification_version_id, sva.attribute_7_name, sva.attribute_7_value, sva.attribute_8_name, sva.attribute_8_value, MAX(sva.version_id)
            FROM specification_versions sv
	    JOIN specification_version_attributes sva ON sva.specification_id = sv.specification_id 
	    WHERE (" . join(" OR ", $column_like_array) . ") AND sv.specification_name NOT LIKE 'IA%' AND sva.attribute_6_value != 1 AND sv.functional_areas LIKE '%$functional_resolved%'  
	    AND sva.version_id = (SELECT MAX(sva.version_id) FROM specification_version_attributes sva WHERE sva.specification_id = sv.specification_id)
	    GROUP BY sv.specification_id)

    AS Results ORDER BY $order_by DESC";

    }

    // Name
    // Type
    // Functional Definition
    // Functional Areas
    // Related Definitions
    // Related Dashboards/Reports
    function query_definitions($search_input, $sort_by, $functional_area) {

        $sorting_decision = "ratio DESC";

        if (0 == strcmp('relevance', $sort_by)) {
            $column_like_array = array();
            $input = explode(" ", $search_input);
            usort($input, 'sort');

            foreach ($input as $input_word) {
                array_push($column_like_array, " (name LIKE '%$input_word%') ");
            }

            if (0 == strcmp('', $search_input)) {
                $sorting_decision = "name ASC";
            }

            $functional_resolved = $this->functional_areas_table[$functional_area];

            return "SELECT *, count_words / ratio_words AS ratio FROM
                (
                    SELECT DISTINCT definition_id, name, functional_definition, functional_areas , (" . join(" + ", $column_like_array) . ") AS count_words, LENGTH(name) + 1 - (LENGTH(REPLACE(name, ' ', ''))) AS ratio_words FROM definition_versions WHERE (" . join(" OR ", $column_like_array) . ") AND version_latest_approved = 1 AND name NOT LIKE 'IA%' AND functional_areas LIKE '%$functional_resolved%' ORDER BY count_words DESC
                ) AS Results
                ORDER BY $sorting_decision, functional_areas";
        }
    }

    /**
     * DEPRECATED:
     * Retrieving related reports from a redis relation mapping
     */
    // Name
    // Type
    // Description
    // Functional Areas
    // Related Dashboards/Reports
    function get_reports_by_definition($definition_id) {
        return "SELECT DISTINCT sv.specification_id, sv.specification_name, sv.specification_type, sv.description, sv.functional_areas, sva.attribute_4_name, sva.attribute_4_value, sva.attribute_5_value AS last_revised, sva.attribute_7_name, sva.attribute_7_value, sva.attribute_8_name, sva.attribute_8_value FROM specification_versions sv JOIN specification_related_definitions srd on srd.definition_id = $definition_id AND srd.specification_id = sv.specification_id LEFT JOIN specification_version_attributes sva ON sva.specification_id = sv.specification_id WHERE sv.specification_name NOT LIKE 'IA%' GROUP BY sv.specification_id ORDER BY sv.specification_name ASC";
    }

    function get_all_relations() {
        //return "SELECT specification_id, definition_id FROM specification_related_definitions WHERE specification_name NOT LIKE 'IA%' AND definition_name NOT LIKE 'IA%' GROUP BY specification_id";
	return "SELECT DISTINCT sv.specification_id, srd.definition_id FROM specification_versions sv JOIN specification_related_definitions srd ON srd.specification_id = sv.specification_id WHERE sv.specification_name NOT LIKE 'IA%'";
    }

    // Select functional areas with existing entries
    function get_specification_functional_areas() {
        return "SELECT DISTINCT sv.functional_areas FROM specification_versions sv WHERE sv.specification_name NOT LIKE 'IA%' AND sv.functional_areas IS NOT NULL";
    }

    function get_definition_functional_areas() {
        return "SELECT DISTINCT v.functional_areas FROM definition_versions v WHERE v.name NOT LIKE 'IA%' AND v.functional_areas IS NOT NULL";
    }

    function get_specification_types() {
        return "SELECT DISTINCT sv.specification_type FROM specification_versions sv WHERE sv.specification_name NOT LIKE 'IA%' AND sv.specification_type IS NOT NULL";
    }

    // DEPRECATED
    function get_restrictions() {
	return "SELECT DISTINCT sva.attribute_7_value AS access_restriction, MAX(sv.specification_id) FROM specification_version_attributes sva JOIN specification_versions sv ON sv.specification_id = sva.specification_id WHERE sv.specification_name NOT LIKE 'IA%' AND sva.attribute_7_value IS NOT NULL GROUP BY sv.specification_id";
    }

    function sort($a, $b) {
        return strlen($a) - strlen($b);
    }
}
?>
