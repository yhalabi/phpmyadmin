<?php
/* $Id$ */

/**
 * Gets the variables sent or posted to this script, then displays headers
 */
if (!isset($selected_tbl)) {
    include('./libraries/grab_globals.lib.php3');
    include('./header.inc.php3');
}

/**
 * Gets the relations settings
 */
require('./libraries/relation.lib.php3');
/**
 * Defines the url to return to in case of error in a sql statement
 */
if (isset($table)) {
    $err_url = 'tbl_properties.php3'
             . '?lang=' . $lang
             . '&amp;convcharset=' . $convcharset
             . '&amp;server=' . $server
             . '&amp;db=' . urlencode($db)
             . '&amp;table=' . urlencode($table);
} else {
    $err_url = 'db_details.php3'
             . '?lang=' . $lang
             . '&amp;convcharset=' . $convcharset
             . '&amp;server=' . $server
             . '&amp;db=' . urlencode($db);
}
/**
 * Selects the database
 */
PMA_mysql_select_db($db);
$sql="show tables from $db";
$rowset=mysql_query($sql);
$count=0;
while ($row=mysql_fetch_array($rowset)) {
    if (PMA_MYSQL_INT_VERSION >= 32303) {
        $myfieldname="Tables_in_".$db;
    }
    else {
        $myfieldname="Tables in ".$db;
    }
    $table=$row[$myfieldname];
    $cfgRelation  = PMA_getRelationsParam();
    if ($cfgRelation['commwork']) {
        $comments = PMA_getComments($db, $table);
    }

    if ($count!=0){
        echo "<p style='page-break-before:always'>";
    }
    echo '<h1>' . $table . '</h1>' . "\n";

          /**
           * Gets table informations
           */
          // The 'show table' statement works correct since 3.23.03
    if (PMA_MYSQL_INT_VERSION >= 32303) {
         $local_query  = 'SHOW TABLE STATUS LIKE \'' . PMA_sqlAddslashes($table, TRUE) . '\'';
         $result       = PMA_mysql_query($local_query) or PMA_mysqlDie('', $local_query, '', $err_url);
         $showtable    = PMA_mysql_fetch_array($result);
         $num_rows     = (isset($showtable['Rows']) ? $showtable['Rows'] : 0);
         $show_comment = (isset($showtable['Comment']) ? $showtable['Comment'] : '');
    } else {
         $local_query  = 'SELECT COUNT(*) AS count FROM ' . PMA_backquote($table);
         $result       = PMA_mysql_query($local_query) or PMA_mysqlDie('', $local_query, '', $err_url);
         $showtable    = array();
         $num_rows     = PMA_mysql_result($result, 0, 'count');
         $show_comment = '';
    } // end display comments
    if ($result) {
         mysql_free_result($result);
    }


          /**
           * Gets table keys and retains them
           */
    $local_query  = 'SHOW KEYS FROM ' . PMA_backquote($table);
    $result       = PMA_mysql_query($local_query) or PMA_mysqlDie('', $local_query, '', $err_url);
    $primary      = '';
    $indexes      = array();
    $lastIndex    = '';
    $indexes_info = array();
    $indexes_data = array();
    $pk_array     = array(); // will be use to emphasis prim. keys in the table
                                   // view
    while ($row = PMA_mysql_fetch_array($result)) {
              // Backups the list of primary keys
        if ($row['Key_name'] == 'PRIMARY') {
            $primary .= $row['Column_name'] . ', ';
            $pk_array[$row['Column_name']] = 1;
        }
             // Retains keys informations
        if ($row['Key_name'] != $lastIndex ){
            $indexes[] = $row['Key_name'];
            $lastIndex = $row['Key_name'];
        }
        $indexes_info[$row['Key_name']]['Sequences'][]     = $row['Seq_in_index'];
        $indexes_info[$row['Key_name']]['Non_unique']      = $row['Non_unique'];
        if (isset($row['Cardinality'])) {
            $indexes_info[$row['Key_name']]['Cardinality'] = $row['Cardinality'];
        }
      //      I don't know what does following column mean....
      //      $indexes_info[$row['Key_name']]['Packed']          = $row['Packed'];
        $indexes_info[$row['Key_name']]['Comment']         = $row['Comment'];

        $indexes_data[$row['Key_name']][$row['Seq_in_index']]['Column_name']  = $row['Column_name'];
        if (isset($row['Sub_part'])) {
            $indexes_data[$row['Key_name']][$row['Seq_in_index']]['Sub_part'] = $row['Sub_part'];
        }

    } // end while
    if ($result) {
        mysql_free_result($result);
    }


          /**
           * Gets fields properties
           */
    $local_query = 'SHOW FIELDS FROM ' . PMA_backquote($table);
    $result      = PMA_mysql_query($local_query) or PMA_mysqlDie('', $local_query, '', $err_url);
    $fields_cnt  = mysql_num_rows($result);

          // Check if we can use Relations (Mike Beck)
    if (!empty($cfgRelation['relation'])) {
              // Find which tables are related with the current one and write it in
              // an array
        $res_rel = PMA_getForeigners($db, $table);

        if (count($res_rel) > 0) {
            $have_rel = TRUE;
        } else {
            $have_rel = FALSE;
        }
    }
    else {
        $have_rel = FALSE;
    } // end if


          /**
           * Displays the comments of the table if MySQL >= 3.23
           */
    if (!empty($show_comment)) {
        echo $strTableComments . '&nbsp;:&nbsp;' . $show_comment . '<br /><br />';
    }

          /**
           * Displays the table structure
           */
          ?>

      <!-- TABLE INFORMATIONS -->
      <table width=100% bordercolorlight=black border style='border-collapse:collapse;background-color:white'>
      <tr>
          <th width=50><?php echo ucfirst($strField); ?></th>
          <th width=50><?php echo ucfirst($strType); ?></th>
          <!--<th width=50><?php echo ucfirst($strAttr); ?></th>-->
          <th width=50><?php echo ucfirst($strNull); ?></th>
          <th width=50><?php echo ucfirst($strDefault); ?></th>
          <!--<th width=50><?php echo ucfirst($strExtra); ?></th>-->
          <?php
          echo "\n";
          if ($have_rel) {
              echo '    <th width=50>' . ucfirst($strLinksTo) . '</th>' . "\n";
          }
          if ($cfgRelation['commwork']) {
              echo '    <th width=400>' . ucfirst($strComments) . '</th>' . "\n";
          }
          ?>
      </tr>

          <?php
          $i = 0;
          while ($row = PMA_mysql_fetch_array($result)) {
              $bgcolor = ($i % 2) ?$cfg['BgcolorOne'] : $cfg['BgcolorTwo'];
              $i++;

              $type             = $row['Type'];
              // reformat mysql query output - staybyte - 9. June 2001
              // loic1: set or enum types: slashes single quotes inside options
              if (eregi('^(set|enum)\((.+)\)$', $type, $tmp)) {
                  $tmp[2]       = substr(ereg_replace('([^,])\'\'', '\\1\\\'', ',' . $tmp[2]), 1);
                  $type         = $tmp[1] . '(' . str_replace(',', ', ', $tmp[2]) . ')';
                  $type_nowrap  = '';
              } else {
                  $type_nowrap  = ' nowrap="nowrap"';
              }
              $type             = eregi_replace('BINARY', '', $type);
              $type             = eregi_replace('ZEROFILL', '', $type);
              $type             = eregi_replace('UNSIGNED', '', $type);
              if (empty($type)) {
                  $type         = '&nbsp;';
              }

              $binary           = eregi('BINARY', $row['Type'], $test);
              $unsigned         = eregi('UNSIGNED', $row['Type'], $test);
              $zerofill         = eregi('ZEROFILL', $row['Type'], $test);
              $strAttribute     = '&nbsp;';
              if ($binary) {
                  $strAttribute = 'BINARY';
              }
              if ($unsigned) {
                  $strAttribute = 'UNSIGNED';
              }
              if ($zerofill) {
                  $strAttribute = 'UNSIGNED ZEROFILL';
              }
              if (!isset($row['Default'])) {
                  if ($row['Null'] != '') {
                      $row['Default'] = '<i>NULL</i>';
                  }
              } else {
                  $row['Default'] = htmlspecialchars($row['Default']);
              }
              $field_name = htmlspecialchars($row['Field']);
              echo "\n";
              ?>
      <tr>
          <td width=50 class='print' nowrap="nowrap">
          <?php
          if (isset($pk_array[$row['Field']])) {
              echo '    <u>' . $field_name . '</u>&nbsp;' . "\n";
          } else {
              echo '    ' . $field_name . '&nbsp;' . "\n";
          }
          ?>
          </td>
          <td width=50 class='print' <?php echo $type_nowrap; ?>><?php echo $type; ?><bdo dir="ltr"></bdo></td>
          <!--<td width=50 bgcolor="<?php echo $bgcolor; ?>" nowrap="nowrap"><?php echo $strAttribute; ?></td>-->
          <td width=50 class='print'><?php echo (($row['Null'] == '') ? $strNo : $strYes); ?>&nbsp;</td>
          <td width=50 class='print' nowrap="nowrap"><?php if (isset($row['Default'])) echo $row['Default']; ?>&nbsp;</td>
          <!--<td width=50 bgcolor="<?php echo $bgcolor; ?>" nowrap="nowrap"><?php echo $row['Extra']; ?>&nbsp;</td>-->
          <?php
          echo "\n";
          if ($have_rel) {
              echo '    <td width=50 class="print" >';
                      if (isset($res_rel[$field_name])) {
                  echo htmlspecialchars($res_rel[$field_name]['foreign_table'] . ' -> ' . $res_rel[$field_name]['foreign_field'] );
              }
              echo '&nbsp;</td>' . "\n";
          }
          if ($cfgRelation['commwork']) {
              echo '    <td width=400 class="print" >';
              if (isset($comments[$field_name])) {
                  echo htmlspecialchars($comments[$field_name]);
              }
              echo '&nbsp;</td>' . "\n";
          }
          ?>
      </tr>
              <?php
          } // end while
          mysql_free_result($result);

          echo "\n";
          ?>
      </table>
      <?echo '</div>' . "\n";


      $count++;
}//ends main while
/**
 * Displays the footer
 */
echo "\n";
echo '<br><br>&nbsp;<input type="button" style="visibility:;width:100px;height:25px" name="print" value="' . $strPrint . '" onclick="printPage()">';
require('./footer.inc.php3');
?>
<script type="text/javascript" language="javascript1.2">
<!--
function printPage()
{
    document.all.print.style.visibility='hidden';
    // Do print the page
    if (typeof(window.print) != 'undefined') {
        window.print();
    }
    document.all.print.style.visibility='';
}
//-->
</script>
