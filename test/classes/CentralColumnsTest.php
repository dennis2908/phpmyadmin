<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * tests for PhpMyAdmin\CentralColumns
 *
 * @package PhpMyAdmin-test
 */
namespace PhpMyAdmin\Tests;

use PhpMyAdmin\CentralColumns;
use PhpMyAdmin\Config;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Types;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

$GLOBALS['server'] = 1;

/**
 * tests for PhpMyAdmin\CentralColumns
 *
 * @package PhpMyAdmin-test
 */
class CentralColumnsTest extends TestCase
{
    private $centralColumns;

    private $_columnData = array(
        array(
            'col_name' => "id", "col_type" => 'integer',
            'col_length' => 0, 'col_isNull' => 0,
            'col_extra' => 'UNSIGNED,auto_increment',
            'col_default' => 1, 'col_collation' => ''
        ),
        array('col_name' => "col1", 'col_type' => 'varchar',
            'col_length' => 100, 'col_isNull' => 1, 'col_extra' => 'BINARY',
            'col_default' => 1, 'col_collation' => ''
        ),
        array(
            'col_name' => "col2", 'col_type' => 'DATETIME',
            'col_length' => 0, 'col_isNull' => 1,
            'col_extra' => 'on update CURRENT_TIMESTAMP',
            'col_default' => 'CURRENT_TIMESTAMP', 'col_collation' => ''
        )
    );

    private $_modifiedColumnData = array(
        array(
            'col_name' => "id", "col_type" => 'integer',
            'col_length' => 0, 'col_isNull' => 0, 'col_extra' => 'auto_increment',
            'col_default' => 1, 'col_collation' => '', 'col_attribute' => 'UNSIGNED'
        ),
        array('col_name' => "col1", 'col_type' => 'varchar',
            'col_length' => 100, 'col_isNull' => 1, 'col_extra' => '',
            'col_default' => 1, 'col_collation' => '', 'col_attribute' => 'BINARY'
        ),
        array(
            'col_name' => "col2", 'col_type' => 'DATETIME',
            'col_length' => 0, 'col_isNull' => 1, 'col_extra' => '',
            'col_default' => 'CURRENT_TIMESTAMP', 'col_collation' => '',
            'col_attribute' => 'on update CURRENT_TIMESTAMP'
        )
    );

    /**
     * prepares environment for tests
     *
     * @return void
     */
    public function setUp()
    {
        $GLOBALS['PMA_Config'] = new Config();
        $GLOBALS['cfg']['Server']['user'] = 'pma_user';
        $GLOBALS['cfg']['Server']['DisableIS'] = true;
        $GLOBALS['cfg']['MaxRows'] = 10;
        $GLOBALS['cfg']['ServerDefault'] = "PMA_server";
        $GLOBALS['cfg']['ActionLinksMode'] = 'icons';
        $GLOBALS['cfg']['CharEditing'] = '';
        $GLOBALS['cfg']['LimitChars'] = 50;
        $GLOBALS['db'] = 'PMA_db';
        $GLOBALS['table'] = 'PMA_table';

        //$_SESSION
        $GLOBALS['server'] = 1;
        $_SESSION['relation'][1] = array(
            'PMA_VERSION' => PMA_VERSION,
            'centralcolumnswork' => true,
            'relwork' => 1,
            'db' => 'phpmyadmin',
            'relation' => 'relation',
            'central_columns' => 'pma_central_columns'
        );

        // mock DBI
        $dbi = $this->getMockBuilder('PhpMyAdmin\DatabaseInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $dbi->types = new Types($dbi);
        $GLOBALS['dbi'] = $dbi;

        // set some common expectations
        $dbi->expects($this->any())
            ->method('selectDb')
            ->will($this->returnValue(true));
        $dbi->expects($this->any())
            ->method('getColumns')
            ->will(
                $this->returnValue(
                    array(
                        "id"=>array("Type"=>"integer", "Null"=>"NO"),
                        "col1"=>array("Type"=>'varchar(100)', "Null"=>"YES"),
                        "col2"=>array("Type"=>'DATETIME', "Null"=>"NO")
                    )
                )
            );
        $dbi->expects($this->any())
            ->method('getColumnNames')
            ->will($this->returnValue(array("id", "col1", "col2")));
        $dbi->expects($this->any())
            ->method('tryQuery')
            ->will($this->returnValue(true));
        $dbi->expects($this->any())
            ->method('getTables')
            ->will(
                $this->returnValue(array("PMA_table", "PMA_table1", "PMA_table2"))
            );
        $dbi->expects($this->any())->method('escapeString')
            ->will($this->returnArgument(0));

        $this->centralColumns = new CentralColumns();
    }

    /**
     * Call protected functions by setting visibility to public.
     *
     * @param string         $name   method name
     * @param array          $params parameters for the invocation
     * @param CentralColumns $object CentralColumns instance object
     *
     * @return mixed the output from the protected method.
     */
    private function callProtectedMethod(
        $name,
        array $params = [],
        CentralColumns $object = null
    ) {
        $class = new ReflectionClass(CentralColumns::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs(
            $object !== null ? $object : $this->centralColumns,
            $params
        );
    }

    /**
     * Test for getParams
     *
     * @return void
     */
    public function testPMACentralColumnsGetParams()
    {
        $this->assertSame(
            array(
                'user' => 'pma_user',
                'db' => 'phpmyadmin',
                'table' => 'pma_central_columns'
            ),
            $this->centralColumns->getParams($GLOBALS['cfg']['Server']['user'])
        );
    }

    /**
     * Test for getColumnsList
     *
     * @return void
     */
    public function testPMAGetColumnsList()
    {
        $GLOBALS['dbi']->expects($this->exactly(2))
            ->method('fetchResult')
            ->willReturnOnConsecutiveCalls(
                $this->_columnData,
                array_slice($this->_columnData, 1, 2)
            );

        $this->assertEquals(
            $this->_modifiedColumnData,
            $this->centralColumns->getColumnsList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin'
            )
        );
        $this->assertEquals(
            array_slice($this->_modifiedColumnData, 1, 2),
            $this->centralColumns->getColumnsList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin',
                1,
                2
            )
        );
    }

    /**
     * Test for getCount
     *
     * @return void
     */
    function testPMAGetCentralColumnsCount()
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT count(db_name) FROM `pma_central_columns` "
                . "WHERE db_name = 'phpmyadmin';",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array(3))
            );

        $this->assertEquals(
            3,
            $this->centralColumns->getCount(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin'
            )
        );
    }

    /**
     * Test for syncUniqueColumns
     *
     * @return void
     */
    public function testPMASyncUniqueColumns()
    {
        $_REQUEST['db'] = 'PMA_db';
        $_REQUEST['table'] = 'PMA_table';

        $this->assertTrue(
            $this->centralColumns->syncUniqueColumns(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                array('PMA_table')
            )
        );
    }

    /**
     * Test for deleteColumnsFromList
     *
     * @return void
     */
    public function testPMADeleteColumnsFromList()
    {
        $_REQUEST['db'] = 'PMA_db';
        $_REQUEST['table'] = 'PMA_table';

        // when column exists in the central column list
        $GLOBALS['dbi']->expects($this->at(4))
            ->method('fetchResult')
            ->with(
                "SELECT col_name FROM `pma_central_columns` "
                . "WHERE db_name = 'PMA_db' AND col_name IN ('col1');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array('col1'))
            );

        $GLOBALS['dbi']->expects($this->at(7))
            ->method('tryQuery')
            ->with(
                "DELETE FROM `pma_central_columns` "
                . "WHERE db_name = 'PMA_db' AND col_name IN ('col1');",
                DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array('col1'))
            );

        $this->assertTrue(
            $this->centralColumns->deleteColumnsFromList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                array("col1"),
                false
            )
        );

        // when column does not exist in the central column list
        $this->assertInstanceOf(
            'PhpMyAdmin\Message', $this->centralColumns->deleteColumnsFromList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                array('column1'),
                false
            )
        );

        $this->assertInstanceOf(
            'PhpMyAdmin\Message', $this->centralColumns->deleteColumnsFromList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                array('PMA_table')
            )
        );
    }

    /**
     * Test for makeConsistentWithList
     *
     * @return void
     */
    public function testPMAMakeConsistentWithList()
    {
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchResult')
            ->will(
                $this->returnValue($this->_columnData)
            );
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchValue')
            ->will(
                $this->returnValue('PMA_table=CREATE table `PMA_table` (id integer)')
            );
        $this->assertTrue(
            $this->centralColumns->makeConsistentWithList(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                "phpmyadmin",
                array('PMA_table')
            )
        );
    }

    /**
     * Test for getFromTable
     *
     * @return void
     */
    public function testPMAGetCentralColumnsFromTable()
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT col_name FROM `pma_central_columns` "
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array('id','col1'))
            );
        $this->assertEquals(
            array("id", "col1"),
            $this->centralColumns->getFromTable(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                $db,
                $table
            )
        );
    }

    /**
     * Test for getFromTable with $allFields = true
     *
     * @return void
     */
    public function testPMAGetCentralColumnsFromTableWithAllFields()
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` "
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array_slice($this->_columnData, 0, 2))
            );
        $this->assertEquals(
            array_slice($this->_modifiedColumnData, 0, 2),
            $this->centralColumns->getFromTable(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                $db,
                $table,
                true
            )
        );
    }

    /**
     * Test for updateOneColumn
     *
     * @return void
     */
    public function testPMAUpdateOneColumn()
    {
        $this->assertTrue(
            $this->centralColumns->updateOneColumn(
                $GLOBALS['dbi'], $GLOBALS['cfg']['Server']['user'],
                "phpmyadmin", "", "", "", "", "", "", "", "", ""
            )
        );
        $this->assertTrue(
            $this->centralColumns->updateOneColumn(
                $GLOBALS['dbi'], $GLOBALS['cfg']['Server']['user'],
                "phpmyadmin", "col1", "", "", "", "", "", "", "", ""
            )
        );
    }

    /**
     * Test for updateMultipleColumn
     *
     * @return void
     */
    public function testPMAUpdateMultipleColumn()
    {
        $_POST['db'] = 'phpmyadmin';
        $_POST['orig_col_name'] = array("col1","col2");
        $_POST['field_name'] = array("col1","col2");
        $_POST['field_default_type'] = array("","");
        $_POST['col_extra'] = array("","");
        $_POST['field_length'] = array("","");
        $_POST['field_attribute'] = array("","");
        $_POST['field_type'] = array("","");
        $_POST['field_collation'] = array("","");
        $this->assertTrue(
            $this->centralColumns->updateMultipleColumn(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user']
            )
        );

    }

    /**
     * Test for getHtmlForEditingPage
     *
     * @return void
     */
    public function testPMAGetHTMLforEditingPage()
    {
        $GLOBALS['dbi']->expects($this->any())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` "
                . "WHERE db_name = 'phpmyadmin' AND col_name IN ('col1','col2');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->_columnData)
            );
        $result = $this->centralColumns->getHtmlForEditingPage(
            $GLOBALS['dbi'],
            $GLOBALS['cfg']['Server']['user'],
            $GLOBALS['cfg']['MaxRows'],
            $GLOBALS['cfg']['CharEditing'],
            $GLOBALS['cfg']['Server']['DisableIS'],
            array("col1", "col2"),
            'phpmyadmin'
        );
        $this->assertContains(
            '<form',
            $result
        );
        $header_cells = array(
        __('Name'), __('Type'), __('Length/Values'), __('Default'),
        __('Collation'), __('Attributes'), __('Null'), __('A_I')
        );
        $this->assertContains(
            $this->callProtectedMethod(
                'getEditTableHeader',
                [$header_cells]
            ),
            $result
        );
        $list_detail_cols = $this->callProtectedMethod(
            'findExistingColNames',
            [
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin',
                "'col1','col2'",
                true,
            ]
        );
        $this->assertContains(
            $this->callProtectedMethod(
                'getHtmlForEditTableRow',
                [
                    $GLOBALS['dbi'],
                    $GLOBALS['cfg']['MaxRows'],
                    $GLOBALS['cfg']['CharEditing'],
                    $GLOBALS['cfg']['Server']['DisableIS'],
                    $list_detail_cols[0],
                    0,
                ]
            ),
            $result
        );
        $this->assertContains(
            $this->callProtectedMethod('getEditTableFooter'),
            $result
        );

    }

    /**
     * Test for getHtmlForTableNavigation
     *
     * @return void
     */
    public function testPMAGetHTMLforTableNavigation()
    {
        $result = $this->centralColumns->getHtmlForTableNavigation(
            $GLOBALS['cfg']['MaxRows'],
            0,
            0,
            'phpmyadmin'
        );
        $this->assertContains(
            '<table',
            $result
        );
        $this->assertContains(
            __('Search this table'),
            $result
        );
        $result_1 = $this->centralColumns->getHtmlForTableNavigation(
            $GLOBALS['cfg']['MaxRows'],
            25,
            10,
            'phpmyadmin'
        );
        $this->assertContains(
            '<form action="db_central_columns.php" method="post">'
            . Url::getHiddenInputs(
                'phpmyadmin'
            ),
            $result_1
        );
        $this->assertContains(
            '<input type="submit" name="navig"'
            . ' class="ajax" '
            . 'value="&lt" />',
            $result_1
        );
        $this->assertContains(
            Util::pageselector(
                'pos', 10, 2, 3
            ),
            $result_1
        );
        $this->assertContains(
            '<input type="submit" name="navig"'
            . ' class="ajax" '
            . 'value="&gt" />',
            $result_1
        );
    }

    /**
     * Test for getTableHeader
     *
     * @return void
     */
    public function testPMAGetCentralColumnsTableHeader()
    {
        $this->assertContains(
            '<thead',
            $this->centralColumns->getTableHeader(
                'column_heading', __('Click to sort'), 2
            )
        );
    }

    /**
     * Test for getListRaw
     *
     * @return void
     */
    public function testPMAGetCentralColumnsListRaw()
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` "
                . "WHERE db_name = 'phpmyadmin';",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->_columnData)
            );
        $this->assertEquals(
            json_encode($this->_modifiedColumnData),
            $this->centralColumns->getListRaw(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin',
                ''
            )
        );
    }

    /**
     * Test for getListRaw with a table name
     *
     * @return void
     */
    public function testPMAGetCentralColumnsListRawWithTable()
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` "
                . "WHERE db_name = 'phpmyadmin' AND col_name "
                . "NOT IN ('id','col1','col2');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue($this->_columnData)
            );
        $this->assertEquals(
            json_encode($this->_modifiedColumnData),
            $this->centralColumns->getListRaw(
                $GLOBALS['dbi'],
                $GLOBALS['cfg']['Server']['user'],
                'phpmyadmin',
                'table1'
            )
        );

    }

    /**
     * Test for getHtmlForAddNewColumn
     *
     * @return void
     */
    public function testPMAGetHTMLforAddNewColumn()
    {
        $result = $this->centralColumns->getHtmlForAddNewColumn(
            $GLOBALS['dbi'],
            $GLOBALS['cfg']['MaxRows'],
            $GLOBALS['cfg']['CharEditing'],
            $GLOBALS['cfg']['Server']['DisableIS'],
            'phpmyadmin',
            0
        );
        $this->assertContains(
            '<form',
            $result
        );
        $this->assertContains(
            '<table',
            $result
        );
        $this->assertContains(
            __('Add new column'),
            $result
        );
        $this->assertContains(
            Url::getHiddenInputs('phpmyadmin'),
            $result
        );
    }

    /**
     * Test for configErrorMessage
     *
     * @return void
     */
    public function testPMAConfigErrorMessage()
    {
        $this->assertInstanceOf(
            'PhpMyAdmin\Message',
            $this->callProtectedMethod('configErrorMessage')
        );
    }

    /**
     * Test for findExistingColNames
     *
     * @return void
     */
    public function testPMAFindExistingColNames()
    {
        $GLOBALS['dbi']->expects($this->once())
            ->method('fetchResult')
            ->with(
                "SELECT * FROM `pma_central_columns` WHERE db_name = 'phpmyadmin'"
                . " AND col_name IN ('col1');",
                null, null, DatabaseInterface::CONNECT_CONTROL
            )
            ->will(
                $this->returnValue(array_slice($this->_columnData, 1, 1))
            );
        $this->assertEquals(
            array_slice($this->_modifiedColumnData, 1, 1),
            $this->callProtectedMethod(
                'findExistingColNames',
                [
                    $GLOBALS['dbi'],
                    $GLOBALS['cfg']['Server']['user'],
                    'phpmyadmin',
                    "'col1'",
                    true,
                ]
            )
        );
    }

    /**
     * Test for getHtmlForTableDropdown
     *
     * @return void
     */
    public function testPMAGetHTMLforTableDropdown()
    {
        $db = 'PMA_db';
        $result = $this->callProtectedMethod(
            'getHtmlForTableDropdown',
            [
                $GLOBALS['dbi'],
                $db,
            ]
        );
        $this->assertContains(
            '<select name="table-select" id="table-select"',
            $result
        );
        $this->assertContains(
            '<option value="PMA_table"',
            $result
        );
    }

    /**
     * Test for getHtmlForColumnDropdown
     *
     * @return void
     */
    public function testPMAGetHTMLforColumnDropdown()
    {
        $db = 'PMA_db';
        $selected_tbl = 'PMA_table';
        $result = $this->centralColumns->getHtmlForColumnDropdown(
            $GLOBALS['dbi'],
            $GLOBALS['cfg']['Server']['user'],
            $db,
            $selected_tbl
        );
        $this->assertEquals(
            '<option value="id">id</option><option value="col1">col1</option>'
            . '<option value="col2">col2</option>',
            $result
        );
    }

    /**
     * Test for getHtmlForAddColumn
     *
     * @return void
     */
    public function testPMAGetHTMLforAddColumn()
    {
        $result = $this->centralColumns->getHtmlForAddColumn($GLOBALS['dbi'], 20, 0, 'phpmyadmin');
        $this->assertContains(
            '<table',
            $result
        );
        $this->assertContains(
            '<form',
            $result
        );
        $this->assertContains(
            Url::getHiddenInputs('phpmyadmin')
            . '<input type="hidden" name="add_column" value="add">'
            . '<input type="hidden" name="pos" value="0" />'
            . '<input type="hidden" name="total_rows" value="20"/>',
            $result
        );
    }

    /**
     * Test for getTableFooter
     *
     * @return void
     */
    public function testPMAGetCentralColumnsTableFooter()
    {
        $pmaThemeImage = "pmaThemeImage";
        $text_dir = "text_dir";
        $result = $this->centralColumns->getTableFooter($pmaThemeImage, $text_dir);
        $this->assertContains(
            '<input type="checkbox" id="tableslistcontainer_checkall" class="checkall_box"',
            $result
        );
        $this->assertContains("With selected:", $result);
        $this->assertContains(
            '<button class="mult_submit change_central_columns"',
            $result
        );
    }
}
