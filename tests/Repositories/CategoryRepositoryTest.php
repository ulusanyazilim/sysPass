<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Tests\Repositories;

use SP\Core\Exceptions\ConstraintException;
use SP\Core\Exceptions\QueryException;
use SP\DataModel\CategoryData;
use SP\DataModel\ItemSearchData;
use SP\Repositories\Category\CategoryRepository;
use SP\Repositories\DuplicatedItemException;
use SP\Storage\Database\DatabaseConnectionData;
use SP\Tests\DatabaseTestCase;
use function SP\Tests\setupContext;

/**
 * Class CategoryRepositoryTest
 *
 * Tests de integración para comprobar las consultas a la BBDD relativas a las categorías
 *
 * @package SP\Tests
 */
class CategoryRepositoryTest extends DatabaseTestCase
{
    /**
     * @var CategoryRepository
     */
    private static $repository;

    /**
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Context\ContextException
     * @throws \DI\DependencyException
     */
    public static function setUpBeforeClass()
    {
        $dic = setupContext();

        self::$dataset = 'syspass.xml';

        // Datos de conexión a la BBDD
        self::$databaseConnectionData = $dic->get(DatabaseConnectionData::class);

        // Inicializar el repositorio
        self::$repository = $dic->get(CategoryRepository::class);
    }

    /**
     * Comprobar los resultados de obtener las categorías por nombre
     *
     * @throws ConstraintException
     * @throws QueryException
     */
    public function testGetByName()
    {
        $this->assertNull(self::$repository->getByName('Prueba'));

        $category = self::$repository->getByName('Web');

        $this->assertEquals(1, $category->getId());
        $this->assertEquals('Web sites', $category->getDescription());

        $category = self::$repository->getByName('Linux');

        $this->assertEquals(2, $category->getId());
        $this->assertEquals('Linux server', $category->getDescription());

        // Se comprueba que el hash generado es el mismo en para el nombre 'Web'
        $category = self::$repository->getByName(' web. ');

        $this->assertEquals(1, $category->getId());
        $this->assertEquals('Web sites', $category->getDescription());
    }

    /**
     * Comprobar la búsqueda mediante texto
     *
     * @throws ConstraintException
     * @throws QueryException
     */
    public function testSearch()
    {
        $itemSearchData = new ItemSearchData();
        $itemSearchData->setLimitCount(10);
        $itemSearchData->setSeachString('linux');

        $result = self::$repository->search($itemSearchData);
        $data = $result->getDataAsArray();

        $this->assertEquals(1, $result->getNumRows());
        $this->assertCount(1, $data);
        $this->assertInstanceOf(\stdClass::class, $data[0]);
        $this->assertEquals(2, $data[0]->id);
        $this->assertEquals('Linux server', $data[0]->description);

        $itemSearchData = new ItemSearchData();
        $itemSearchData->setLimitCount(10);
        $itemSearchData->setSeachString('prueba');

        $result = self::$repository->search($itemSearchData);
        $data = $result->getDataAsArray();

        $this->assertEquals(0, $result->getNumRows());
        $this->assertCount(0, $data);
    }

    /**
     * Comprobar los resultados de obtener las categorías por Id
     *
     * @throws ConstraintException
     * @throws QueryException
     */
    public function testGetById()
    {
        $this->assertNull(self::$repository->getById(10));

        $category = self::$repository->getById(1);

        $this->assertEquals('Web', $category->getName());
        $this->assertEquals('Web sites', $category->getDescription());

        $category = self::$repository->getById(2);

        $this->assertEquals('Linux', $category->getName());
        $this->assertEquals('Linux server', $category->getDescription());
    }

    /**
     * Comprobar la obtención de todas las categorías
     *
     * @throws ConstraintException
     * @throws QueryException
     */
    public function testGetAll()
    {
        $count = $this->conn->getRowCount('Category');

        $results = self::$repository->getAll();

        $this->assertCount($count, $results);

        $this->assertInstanceOf(CategoryData::class, $results[0]);
        $this->assertEquals('Linux', $results[0]->getName());

        $this->assertInstanceOf(CategoryData::class, $results[1]);
        $this->assertEquals('SSH', $results[1]->getName());

        $this->assertInstanceOf(CategoryData::class, $results[2]);
        $this->assertEquals('Web', $results[2]->getName());
    }

    /**
     * Comprobar la actualización de categorías
     *
     * @depends testGetById
     * @covers  \SP\Repositories\Category\CategoryRepository::checkDuplicatedOnUpdate()
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\SPException
     * @throws \SP\Repositories\DuplicatedItemException
     */
    public function testUpdate()
    {
        $categoryData = new CategoryData();
        $categoryData->id = 1;
        $categoryData->name = 'Web prueba';
        $categoryData->description = 'Descripción web prueba';

        self::$repository->update($categoryData);

        $category = self::$repository->getById(1);

        $this->assertEquals($category->getName(), $categoryData->name);
        $this->assertEquals($category->getDescription(), $categoryData->description);

        // Comprobar la a actualización con un nombre duplicado comprobando su hash
        $categoryData = new CategoryData();
        $categoryData->id = 1;
        $categoryData->name = ' linux.';

        $this->expectException(DuplicatedItemException::class);

        self::$repository->update($categoryData);
    }

    /**
     * Comprobar la eliminación de categorías
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testDeleteByIdBatch()
    {
        $countBefore = $this->conn->getRowCount('Category');

        $this->assertEquals(1, self::$repository->deleteByIdBatch([3]));

        $countAfter = $this->conn->getRowCount('Category');

        $this->assertEquals($countBefore - 1, $countAfter);

        // Comprobar que se produce una excepción al tratar de eliminar categorías usadas
        $this->expectException(ConstraintException::class);

        $this->assertEquals(1, self::$repository->deleteByIdBatch([1, 2, 3]));
    }

    /**
     * Comprobar la creación de categorías
     *
     * @depends testGetById
     * @covers  \SP\Repositories\Category\CategoryRepository::checkDuplicatedOnAdd()
     * @throws DuplicatedItemException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function testCreate()
    {
        $countBefore = $this->conn->getRowCount('Category');

        $categoryData = new CategoryData();
        $categoryData->name = 'Categoría prueba';
        $categoryData->description = 'Descripción prueba';

        $id = self::$repository->create($categoryData);

        // Comprobar que el Id devuelto corresponde con la categoría creada
        $category = self::$repository->getById($id);

        $this->assertEquals($categoryData->name, $category->getName());

        $countAfter = $this->conn->getRowCount('Category');

        $this->assertEquals($countBefore + 1, $countAfter);
    }

    /**
     * Comprobar la eliminación de categorías por Id
     *
     * @throws QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function testDelete()
    {
        $countBefore = $this->conn->getRowCount('Category');

        $this->assertEquals(1, self::$repository->delete(3));

        $countAfter = $this->conn->getRowCount('Category');

        $this->assertEquals($countBefore - 1, $countAfter);

        // Comprobar que se produce una excepción al tratar de eliminar categorías usadas
        $this->expectException(ConstraintException::class);

        $this->assertEquals(1, self::$repository->delete(2));
    }

    /**
     * Comprobar la obtención de categorías por Id en lote
     *
     * @throws ConstraintException
     * @throws QueryException
     */
    public function testGetByIdBatch()
    {
        $this->assertCount(3, self::$repository->getByIdBatch([1, 2, 3]));
        $this->assertCount(3, self::$repository->getByIdBatch([1, 2, 3, 4, 5]));
        $this->assertCount(0, self::$repository->getByIdBatch([]));
    }
}
