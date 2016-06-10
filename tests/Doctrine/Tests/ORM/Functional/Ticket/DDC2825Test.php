<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Tests\Models\DDC2825\ExplicitSchemaAndTable;
use Doctrine\Tests\Models\DDC2825\SchemaAndTableInTableName;

/**
 * This class makes tests on the correct use of a database schema when entities are stored
 *
 * @group DDC-2825
 */
class DDC2825Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        $this->enableQuotes = true;

        parent::setup();

        $platform = $this->_em->getConnection()->getDatabasePlatform();

        if ( ! $platform->supportsSchemas() && ! $platform->canEmulateSchemas()) {
            $this->markTestSkipped("This test is only useful for databases that support schemas or can emulate them.");
        }
    }

    /**
     * @dataProvider getTestedClasses
     *
     * @param string $className
     * @param string $expectedSchemaName
     * @param string $expectedTableName
     */
    public function testClassSchemaMappingsValidity($className, $expectedSchemaName, $expectedTableName)
    {
        $classMetadata   = $this->_em->getClassMetadata($className);
        $platform        = $this->_em->getConnection()->getDatabasePlatform();
        $quotedTableName = $this->_em->getConfiguration()->getQuoteStrategy()->getTableName($classMetadata, $platform);

        // Check if table name and schema properties are defined in the class metadata
        self::assertEquals($expectedTableName, $classMetadata->table['name']);
        self::assertEquals($expectedSchemaName, $classMetadata->table['schema']);

        if ($platform->supportsSchemas()) {
            $fullTableName = sprintf('%s.%s', $expectedSchemaName, $expectedTableName);
        } else {
            $fullTableName = sprintf('%s__%s', $expectedSchemaName, $expectedTableName);
        }

        self::assertEquals($fullTableName, $quotedTableName);

        // Checks sequence name validity
        self::assertEquals(
            $fullTableName . '_' . $classMetadata->getSingleIdentifierColumnName() . '_seq',
            $classMetadata->getSequenceName($platform)
        );
    }

    /**
     * @dataProvider getTestedClasses
     *
     * @param string $className
     */
    public function testPersistenceOfEntityWithSchemaMapping($className)
    {
        $classMetadata = $this->_em->getClassMetadata($className);
        $repository    = $this->_em->getRepository($className);

        try {
            $this->_schemaTool->createSchema(array($classMetadata));
        } catch (ToolsException $e) {
            // table already exists
        }

        $this->_em->persist(new $className());
        $this->_em->flush();
        $this->_em->clear();

        self::assertCount(1, $repository->findAll());
    }

    /**
     * Data provider
     *
     * @return string[][]
     */
    public function getTestedClasses()
    {
        return array(
            array(ExplicitSchemaAndTable::CLASSNAME, 'explicit_schema', 'explicit_table'),
            array(SchemaAndTableInTableName::CLASSNAME, 'implicit_schema', 'implicit_table'),
            array(DDC2825ClassWithImplicitlyDefinedSchemaAndQuotedTableName::CLASSNAME, 'myschema', 'order'),
        );
    }
}

/**
 * @Entity
 * @Table(name="myschema.order")
 */
class DDC2825ClassWithImplicitlyDefinedSchemaAndQuotedTableName
{
    const CLASSNAME = __CLASS__;

    /**
     * @Id @GeneratedValue
     * @Column(type="integer")
     *
     * @var integer
     */
    public $id;
}