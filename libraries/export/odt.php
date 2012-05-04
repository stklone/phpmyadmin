<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Set of functions used to build OpenDocument Text dumps of tables
 *
 * @package    PhpMyAdmin-Export
 * @subpackage ODT
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

/**
 *
 */
if (isset($plugin_list)) {
    $hide_structure = false;
    if ($plugin_param['export_type'] == 'table'
        && ! $plugin_param['single_table']
    ) {
        $hide_structure = true;
    }
    $plugin_list['odt'] = array(
        'text' => __('Open Document Text'),
        'extension' => 'odt',
        'mime_type' => 'application/vnd.oasis.opendocument.text',
        'force_file' => true,
        'options' => array(),
        'options_text' => __('Options')
    );

    /* what to dump (structure/data/both) */
    $plugin_list['odt']['options'][] = array(
        'type' => 'begin_group',
        'text' => __('Dump table'),
        'name' => 'general_opts'
    );
    $plugin_list['odt']['options'][] = array(
        'type' => 'radio',
        'name' => 'structure_or_data',
        'values' => array(
            'structure' => __('structure'),
            'data' => __('data'),
            'structure_and_data' => __('structure and data')
        )
    );
    $plugin_list['odt']['options'][] = array(
        'type' => 'end_group'
    );

    /* Structure options */
    if (! $hide_structure) {
        $plugin_list['odt']['options'][] = array(
            'type' => 'begin_group',
            'name' => 'structure',
            'text' => __('Object creation options'),
            'force' => 'data'
        );
        if (! empty($GLOBALS['cfgRelation']['relation'])) {
            $plugin_list['odt']['options'][] = array(
                'type' => 'bool',
                'name' => 'relation',
                'text' => __('Display foreign key relationships')
            );
        }
        $plugin_list['odt']['options'][] = array(
            'type' => 'bool',
            'name' => 'comments',
            'text' => __('Display comments')
        );
        if (! empty($GLOBALS['cfgRelation']['mimework'])) {
            $plugin_list['odt']['options'][] = array(
                'type' => 'bool',
                'name' => 'mime',
                'text' => __('Display MIME types')
            );
        }
        $plugin_list['odt']['options'][] = array(
            'type' => 'end_group'
        );
    }

    /* Data */
    $plugin_list['odt']['options'][] = array(
        'type' => 'begin_group',
        'name' => 'data',
        'text' => __('Data dump options'),
        'force' => 'structure'
    );
    $plugin_list['odt']['options'][] = array(
        'type' => 'bool',
        'name' => 'columns',
        'text' => __('Put columns names in the first row')
    );
    $plugin_list['odt']['options'][] = array(
        'type' => 'text',
        'name' => 'null',
        'text' => __('Replace NULL with:')
    );
    $plugin_list['odt']['options'][] = array(
        'type' => 'end_group'
    );
} else {

    $GLOBALS['odt_buffer'] = '';
    include_once './libraries/opendocument.lib.php';

    /**
     * Outputs export footer
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportFooter()
    {
        $GLOBALS['odt_buffer'] .= '</office:text>'
            . '</office:body>'
            . '</office:document-content>';
        if (! PMA_exportOutputHandler(
            PMA_createOpenDocument(
                'application/vnd.oasis.opendocument.text',
                $GLOBALS['odt_buffer']
            )
        )) {
            return false;
        }
        return true;
    }

    /**
     * Outputs export header
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportHeader()
    {
        $GLOBALS['odt_buffer'] .= '<?xml version="1.0" encoding="utf-8"?' . '>'
            . '<office:document-content '
                . $GLOBALS['OpenDocumentNS'] . 'office:version="1.0">'
            . '<office:body>'
            . '<office:text>';
        return true;
    }

    /**
     * Outputs database header
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBHeader($db)
    {
        $GLOBALS['odt_buffer'] .=
            '<text:h text:outline-level="1" text:style-name="Heading_1"'
                . ' text:is-list-header="true">'
            . __('Database') . ' ' . htmlspecialchars($db)
            . '</text:h>';
        return true;
    }

    /**
     * Outputs database footer
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBFooter($db)
    {
        return true;
    }

    /**
     * Outputs CREATE DATABASE statement
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBCreate($db)
    {
        return true;
    }

    /**
     * Outputs the content of a table in ODT format
     *
     * @param string $db        database name
     * @param string $table     table name
     * @param string $crlf      the end of line sequence
     * @param string $error_url the url to go back in case of error
     * @param string $sql_query SQL query for obtaining data
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportData($db, $table, $crlf, $error_url, $sql_query)
    {
        global $what;

        // Gets the data from the database
        $result      = PMA_DBI_query($sql_query, null, PMA_DBI_QUERY_UNBUFFERED);
        $fields_cnt  = PMA_DBI_num_fields($result);
        $fields_meta = PMA_DBI_get_fields_meta($result);
        $field_flags = array();
        for ($j = 0; $j < $fields_cnt; $j++) {
            $field_flags[$j] = PMA_DBI_field_flags($result, $j);
        }

        $GLOBALS['odt_buffer'] .=
            '<text:h text:outline-level="2" text:style-name="Heading_2"'
                . ' text:is-list-header="true">'
            . __('Dumping data for table') . ' ' . htmlspecialchars($table)
            . '</text:h>'
            . '<table:table'
            . ' table:name="' . htmlspecialchars($table) . '_structure">'
            . '<table:table-column'
            . ' table:number-columns-repeated="' . $fields_cnt . '"/>';

        // If required, get fields name at the first line
        if (isset($GLOBALS[$what . '_columns'])) {
            $GLOBALS['odt_buffer'] .= '<table:table-row>';
            for ($i = 0; $i < $fields_cnt; $i++) {
                $GLOBALS['odt_buffer'] .=
                    '<table:table-cell office:value-type="string">'
                    . '<text:p>'
                        . htmlspecialchars(
                            stripslashes(PMA_DBI_field_name($result, $i))
                        )
                    . '</text:p>'
                    . '</table:table-cell>';
            } // end for
            $GLOBALS['odt_buffer'] .= '</table:table-row>';
        } // end if

        // Format the data
        while ($row = PMA_DBI_fetch_row($result)) {
            $GLOBALS['odt_buffer'] .= '<table:table-row>';
            for ($j = 0; $j < $fields_cnt; $j++) {
                if (! isset($row[$j]) || is_null($row[$j])) {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p>'
                        . htmlspecialchars($GLOBALS[$what . '_null'])
                        . '</text:p>'
                        . '</table:table-cell>';
                } elseif (stristr($field_flags[$j], 'BINARY')
                    && $fields_meta[$j]->blob
                ) {
                    // ignore BLOB
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p></text:p>'
                        . '</table:table-cell>';
                } elseif ($fields_meta[$j]->numeric
                    && $fields_meta[$j]->type != 'timestamp'
                    && ! $fields_meta[$j]->blob
                ) {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="float"'
                            . ' office:value="' . $row[$j] . '" >'
                        . '<text:p>'
                        . htmlspecialchars($row[$j])
                        . '</text:p>'
                        . '</table:table-cell>';
                } else {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p>'
                        . htmlspecialchars($row[$j])
                        . '</text:p>'
                        . '</table:table-cell>';
                }
            } // end for
            $GLOBALS['odt_buffer'] .= '</table:table-row>';
        } // end while
        PMA_DBI_free_result($result);

        $GLOBALS['odt_buffer'] .= '</table:table>';

        return true;
    }

    /**
     * Returns a stand-in CREATE definition to resolve view dependencies
     *
     * @param string $db   the database name
     * @param string $view the view name
     * @param string $crlf the end of line sequence
     *
     * @return bool true
     *
     * @access public
     */
    function PMA_getTableDefStandIn($db, $view, $crlf)
    {
        /**
         * Gets fields properties
         */
        PMA_DBI_select_db($db);

        /**
         * Displays the table structure
         */
        $GLOBALS['odt_buffer'] .=
            '<table:table table:name="'
            . htmlspecialchars($table) . '_data">';
        $columns_cnt = 4;
        $GLOBALS['odt_buffer'] .=
            '<table:table-column'
            . ' table:number-columns-repeated="' . $columns_cnt . '"/>';
        /* Header */
        $GLOBALS['odt_buffer'] .= '<table:table-row>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Column') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Type') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Null') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Default') . '</text:p>'
            . '</table:table-cell>'
            . '</table:table-row>';

        $columns = PMA_DBI_get_columns($db, $view);
        foreach ($columns as $column) {
            $GLOBALS['odt_buffer'] .= PMA_formatOneColumnDefinition($column);
            $GLOBALS['odt_buffer'] .= '</table:table-row>';
        } // end foreach 

        $GLOBALS['odt_buffer'] .= '</table:table>';
        return true;
    }

    /**
     * Returns $table's CREATE definition
     *
     * @param string $db            the database name
     * @param string $table         the table name
     * @param string $crlf          the end of line sequence
     * @param string $error_url     the url to go back in case of error
     * @param bool   $do_relation   whether to include relation comments
     * @param bool   $do_comments   whether to include the pmadb-style column
     *                                comments as comments in the structure;
     *                                this is deprecated but the parameter is
     *                                left here because export.php calls
     *                                PMA_exportStructure() also for other
     * @param bool   $do_mime       whether to include mime comments
     * @param bool   $show_dates    whether to include creation/update/check dates
     * @param bool   $add_semicolon whether to add semicolon and end-of-line at
     *                              the end
     * @param bool   $view          whether we're handling a view
     *
     * @return bool true
     *
     * @access public
     */
    function PMA_getTableDef(
        $db,
        $table,
        $crlf,
        $error_url,
        $do_relation,
        $do_comments,
        $do_mime,
        $show_dates = false,
        $add_semicolon = true,
        $view = false
    ) {
        global $cfgRelation;

        /**
         * Gets fields properties
         */
        PMA_DBI_select_db($db);

        // Check if we can use Relations
        if ($do_relation && ! empty($cfgRelation['relation'])) {
            // Find which tables are related with the current one and write it in
            // an array
            $res_rel = PMA_getForeigners($db, $table);

            if ($res_rel && count($res_rel) > 0) {
                $have_rel = true;
            } else {
                $have_rel = false;
            }
        } else {
               $have_rel = false;
        } // end if

        /**
         * Displays the table structure
         */
        $GLOBALS['odt_buffer'] .= '<table:table table:name="'
            . htmlspecialchars($table) . '_structure">';
        $columns_cnt = 4;
        if ($do_relation && $have_rel) {
            $columns_cnt++;
        }
        if ($do_comments) {
            $columns_cnt++;
        }
        if ($do_mime && $cfgRelation['mimework']) {
            $columns_cnt++;
        }
        $GLOBALS['odt_buffer'] .= '<table:table-column'
            . ' table:number-columns-repeated="' . $columns_cnt . '"/>';
        /* Header */
        $GLOBALS['odt_buffer'] .= '<table:table-row>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Column') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Type') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Null') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Default') . '</text:p>'
            . '</table:table-cell>';
        if ($do_relation && $have_rel) {
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>' . __('Links to') . '</text:p>'
                . '</table:table-cell>';
        }
        if ($do_comments) {
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>' . __('Comments') . '</text:p>'
                . '</table:table-cell>';
            $comments = PMA_getComments($db, $table);
        }
        if ($do_mime && $cfgRelation['mimework']) {
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>' . __('MIME type') . '</text:p>'
                . '</table:table-cell>';
            $mime_map = PMA_getMIME($db, $table, true);
        }
        $GLOBALS['odt_buffer'] .= '</table:table-row>';

        $columns = PMA_DBI_get_columns($db, $table);
        foreach ($columns as $column) {
            $field_name = $column['Field'];
            $GLOBALS['odt_buffer'] .= PMA_formatOneColumnDefinition($column);

            if ($do_relation && $have_rel) {
                if (isset($res_rel[$field_name])) {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p>'
                        . htmlspecialchars(
                            $res_rel[$field_name]['foreign_table']
                            . ' (' . $res_rel[$field_name]['foreign_field'] . ')'
                        )
                        . '</text:p>'
                        . '</table:table-cell>';
                }
            }
            if ($do_comments) {
                if (isset($comments[$field_name])) {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p>'
                        . htmlspecialchars($comments[$field_name])
                        . '</text:p>'
                        . '</table:table-cell>';
                } else {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p></text:p>'
                        . '</table:table-cell>';
                }
            }
            if ($do_mime && $cfgRelation['mimework']) {
                if (isset($mime_map[$field_name])) {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p>'
                        . htmlspecialchars(
                            str_replace('_', '/', $mime_map[$field_name]['mimetype'])
                        )
                        . '</text:p>'
                        . '</table:table-cell>';
                } else {
                    $GLOBALS['odt_buffer'] .=
                        '<table:table-cell office:value-type="string">'
                        . '<text:p></text:p>'
                        . '</table:table-cell>';
                }
            }
            $GLOBALS['odt_buffer'] .= '</table:table-row>';
        } // end foreach 

        $GLOBALS['odt_buffer'] .= '</table:table>';
        return true;
    } // end of the 'PMA_getTableDef()' function

    /**
     * Outputs triggers
     *
     * @param string $db    database name
     * @param string $table table name
     *
     * @return bool true
     *
     * @access public
     */
    function PMA_getTriggers($db, $table)
    {
        $GLOBALS['odt_buffer'] .= '<table:table'
            . ' table:name="' . htmlspecialchars($table) . '_triggers">'
            . '<table:table-column'
            . ' table:number-columns-repeated="4"/>'
            . '<table:table-row>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Name') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Time') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Event') . '</text:p>'
            . '</table:table-cell>'
            . '<table:table-cell office:value-type="string">'
            . '<text:p>' . __('Definition') . '</text:p>'
            . '</table:table-cell>'
            . '</table:table-row>';

        $triggers = PMA_DBI_get_triggers($db, $table);

        foreach ($triggers as $trigger) {
            $GLOBALS['odt_buffer'] .= '<table:table-row>';
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>'
                . htmlspecialchars($trigger['name'])
                . '</text:p>'
                . '</table:table-cell>';
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>'
                . htmlspecialchars($trigger['action_timing'])
                . '</text:p>'
                . '</table:table-cell>';
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>'
                . htmlspecialchars($trigger['event_manipulation'])
                . '</text:p>'
                . '</table:table-cell>';
            $GLOBALS['odt_buffer'] .= '<table:table-cell office:value-type="string">'
                . '<text:p>'
                . htmlspecialchars($trigger['definition'])
                . '</text:p>'
                . '</table:table-cell>';
            $GLOBALS['odt_buffer'] .= '</table:table-row>';
        }

        $GLOBALS['odt_buffer'] .= '</table:table>';
        return true;
    }

    /**
     * Outputs table's structure
     *
     * @param string $db          database name
     * @param string $table       table name
     * @param string $crlf        the end of line sequence
     * @param string $error_url   the url to go back in case of error
     * @param string $export_mode 'create_table', 'triggers', 'create_view',
     *                            'stand_in'
     * @param string $export_type 'server', 'database', 'table'
     * @param bool   $do_relation whether to include relation comments
     * @param bool   $do_comments whether to include the pmadb-style column
     *                                comments as comments in the structure;
     *                                this is deprecated but the parameter is
     *                                left here because export.php calls
     *                                PMA_exportStructure() also for other
     * @param bool   $do_mime     whether to include mime comments
     * @param bool   $dates       whether to include creation/update/check dates
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportStructure(
        $db,
        $table,
        $crlf,
        $error_url,
        $export_mode,
        $export_type,
        $do_relation = false,
        $do_comments = false,
        $do_mime = false,
        $dates = false
    ) {
        switch($export_mode) {
        case 'create_table':
            $GLOBALS['odt_buffer'] .=
                '<text:h text:outline-level="2" text:style-name="Heading_2"'
                . ' text:is-list-header="true">'
                . __('Table structure for table') . ' ' .
                htmlspecialchars($table)
                . '</text:h>';
            PMA_getTableDef(
                $db, $table, $crlf, $error_url, $do_relation, $do_comments,
                $do_mime, $dates
            );
            break;
        case 'triggers':
            $triggers = PMA_DBI_get_triggers($db, $table);
            if ($triggers) {
                $GLOBALS['odt_buffer'] .=
                    '<text:h text:outline-level="2" text:style-name="Heading_2"'
                    . ' text:is-list-header="true">'
                    . __('Triggers') . ' '
                    . htmlspecialchars($table)
                    . '</text:h>';
                    PMA_getTriggers($db, $table);
            }
            break;
        case 'create_view':
            $GLOBALS['odt_buffer'] .=
                '<text:h text:outline-level="2" text:style-name="Heading_2"'
                . ' text:is-list-header="true">'
                . __('Structure for view') . ' '
                . htmlspecialchars($table)
                . '</text:h>';
            PMA_getTableDef(
                $db, $table, $crlf, $error_url, $do_relation, $do_comments,
                $do_mime, $dates, true, true
            );
            break;
        case 'stand_in':
            $GLOBALS['odt_buffer'] .=
                '<text:h text:outline-level="2" text:style-name="Heading_2"'
                    . ' text:is-list-header="true">'
                . __('Stand-in structure for view') . ' '
                . htmlspecialchars($table)
                . '</text:h>';
            // export a stand-in definition to resolve view dependencies
            PMA_getTableDefStandIn($db, $table, $crlf);
        } // end switch

        return true;
    } // end of the 'PMA_exportStructure' function

    /**
     * Formats the definition for one column 
     *
     * @param array $column info about this column
     *
     * @return string Formatted column definition
     *
     * @access public
     */
    function PMA_formatOneColumnDefinition($column)
    {
        $field_name = $column['Field'];
        $definition =  '<table:table-row>';
        $definition .= '<table:table-cell office:value-type="string">'
            . '<text:p>' . htmlspecialchars($field_name) . '</text:p>'
            . '</table:table-cell>';

        $extracted_columnspec = PMA_extractColumnSpec($column['Type']);
        $type = htmlspecialchars($extracted_columnspec['print_type']);
        if (empty($type)) {
            $type = '&nbsp;';
        }

        $definition .= '<table:table-cell office:value-type="string">'
            . '<text:p>' . htmlspecialchars($type) . '</text:p>'
            . '</table:table-cell>';
        if (! isset($column['Default'])) {
            if ($column['Null'] != 'NO') {
                $column['Default'] = 'NULL';
            } else {
                $column['Default'] = '';
            }
        } else {
            $column['Default'] = $column['Default'];
        }
        $definition .= '<table:table-cell office:value-type="string">'
            . '<text:p>' 
            . (($column['Null'] == '' || $column['Null'] == 'NO') 
                ? __('No') 
                : __('Yes')) 
            . '</text:p>'
            . '</table:table-cell>';
        $definition .= '<table:table-cell office:value-type="string">'
            . '<text:p>' . htmlspecialchars($column['Default']) . '</text:p>'
            . '</table:table-cell>';
        return $definition;
    }
} // end else
?>
