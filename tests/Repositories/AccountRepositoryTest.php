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

use DI\DependencyException;
use SP\Account\AccountRequest;
use SP\Account\AccountSearchFilter;
use SP\Core\Crypt\Crypt;
use SP\Core\Exceptions\SPException;
use SP\DataModel\AccountData;
use SP\DataModel\AccountVData;
use SP\DataModel\Dto\AccountSearchResponse;
use SP\DataModel\ItemSearchData;
use SP\Mvc\Model\QueryCondition;
use SP\Repositories\Account\AccountRepository;
use SP\Services\Account\AccountPasswordRequest;
use SP\Storage\Database\DatabaseConnectionData;
use SP\Tests\DatabaseTestCase;
use function SP\Tests\setupContext;

/**
 * Class AccountRepositoryTest
 *
 * Tests de integración para comprobar las consultas a la BBDD relativas a las cuentas
 *
 * @package SP\Tests
 */
class AccountRepositoryTest extends DatabaseTestCase
{
    const SECURE_KEY_PASSWORD = 'syspass123';
    /**
     * @var AccountRepository
     */
    private static $repository;

    /**
     * @throws DependencyException
     * @throws \DI\NotFoundException
     * @throws \SP\Core\Context\ContextException
     */
    public static function setUpBeforeClass()
    {
        $dic = setupContext();

        self::$dataset = 'syspass_account.xml';

        // Datos de conexión a la BBDD
        self::$databaseConnectionData = $dic->get(DatabaseConnectionData::class);

        // Inicializar el repositorio
        self::$repository = $dic->get(AccountRepository::class);
    }

    /**
     * Comprobar la eliminación de registros
     *
     * @throws SPException
     */
    public function testDelete()
    {
        // Comprobar registros iniciales
        $this->assertEquals(2, $this->conn->getRowCount('Account'));

        // Eliminar un registro y comprobar el total de registros
        $this->assertEquals(1, self::$repository->delete(1));
        $this->assertEquals(1, $this->conn->getRowCount('Account'));

        // Eliminar un registro no existente
        $this->assertEquals(0, self::$repository->delete(100));

        // Eliminar un registro y comprobar el total de registros
        $this->assertEquals(1, self::$repository->delete(2));
        $this->assertEquals(0, $this->conn->getRowCount('Account'));
    }

    /**
     * No implementado
     */
    public function testEditRestore()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * Comprobar la modificación de una clave de cuenta
     *
     * @covers \SP\Repositories\Account\AccountRepository::getPasswordForId()
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function testEditPassword()
    {
        $accountRequest = new AccountRequest();
        $accountRequest->key = Crypt::makeSecuredKey(self::SECURE_KEY_PASSWORD);
        $accountRequest->pass = Crypt::encrypt('1234', $accountRequest->key, self::SECURE_KEY_PASSWORD);
        $accountRequest->id = 2;
        $accountRequest->userEditId = 1;
        $accountRequest->passDateChange = time() + 3600;

        // Comprobar que la modificación de la clave es correcta
        $this->assertEquals(1, self::$repository->editPassword($accountRequest));

        $accountPassData = self::$repository->getPasswordForId(2)->getData();
        $clearPassword = Crypt::decrypt($accountPassData->pass, $accountPassData->key, self::SECURE_KEY_PASSWORD);

        // Comprobar que la clave obtenida es igual a la encriptada anteriormente
        $this->assertEquals('1234', $clearPassword);

        // Comprobar que no devuelve resultados
        $this->assertEquals(0, self::$repository->getPasswordForId(10)->getNumRows());
    }

    /**
     * Comprobar la obtención de cuentas
     *
     * @throws SPException
     */
    public function testGetById()
    {
        $result = self::$repository->getById(1);

        $this->assertEquals(1, $result->getNumRows());

        /** @var AccountVData $data */
        $data = $result->getData();

        $this->assertInstanceOf(AccountVData::class, $data);
        $this->assertEquals(1, $data->getId());

        $this->assertEquals(0, self::$repository->getById(10)->getNumRows());
    }

    /**
     * @depends testGetById
     * @throws SPException
     */
    public function testUpdate()
    {
        $accountRequest = new AccountRequest();
        $accountRequest->id = 1;
        $accountRequest->name = 'Prueba 1';
        $accountRequest->login = 'admin';
        $accountRequest->url = 'http://syspass.org';
        $accountRequest->notes = 'notas';
        $accountRequest->userEditId = 1;
        $accountRequest->passDateChange = time() + 3600;
        $accountRequest->clientId = 1;
        $accountRequest->categoryId = 1;
        $accountRequest->isPrivate = 0;
        $accountRequest->isPrivateGroup = 0;
        $accountRequest->parentId = 0;
        $accountRequest->userGroupId = 2;

        $this->assertEquals(1, self::$repository->update($accountRequest));

        $result = self::$repository->getById(1);

        $this->assertEquals(1, $result->getNumRows());

        /** @var AccountVData $data */
        $data = $result->getData();

        $this->assertEquals(1, $data->getId());
        $this->assertEquals($accountRequest->name, $data->getName());
        $this->assertEquals($accountRequest->login, $data->getLogin());
        $this->assertEquals($accountRequest->url, $data->getUrl());
        $this->assertEquals($accountRequest->notes, $data->getNotes());
        $this->assertEquals($accountRequest->userEditId, $data->getUserEditId());
        $this->assertEquals($accountRequest->passDateChange, $data->getPassDateChange());
        $this->assertEquals($accountRequest->clientId, $data->getClientId());
        $this->assertEquals($accountRequest->categoryId, $data->getCategoryId());
        $this->assertEquals($accountRequest->isPrivate, $data->getIsPrivate());
        $this->assertEquals($accountRequest->isPrivateGroup, $data->getIsPrivateGroup());
        $this->assertEquals($accountRequest->parentId, $data->getParentId());

        // El grupo no debe de cambiar si el usuario no tiene permisos
        $this->assertNotEquals($accountRequest->userGroupId, $data->getUserGroupId());
        $this->assertEquals(1, $data->getUserGroupId());
    }

    /**
     * No implementado
     */
    public function testCheckDuplicatedOnAdd()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * Comprobar la eliminación en lotes
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testDeleteByIdBatch()
    {
        // Comprobar registros iniciales
        $this->assertEquals(2, $this->conn->getRowCount('Account'));

        $this->assertEquals(2, self::$repository->deleteByIdBatch([1, 2, 100]));

        // Comprobar registros tras eliminación
        $this->assertEquals(0, $this->conn->getRowCount('Account'));
    }

    /**
     * Comprobar la búsqueda de cuentas
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testSearch()
    {
        // Comprobar búsqueda con el texto Google Inc
        $itemSearchData = new ItemSearchData();
        $itemSearchData->setSeachString('Google');
        $itemSearchData->setLimitCount(10);

        $result = self::$repository->search($itemSearchData);
        $data = $result->getDataAsArray();

        $this->assertCount(1, $data);
        $this->assertEquals(1, $result->getNumRows());
        $this->assertInstanceOf(\stdClass::class, $data[0]);
        $this->assertEquals(1, $data[0]->id);
        $this->assertEquals('Google', $data[0]->name);

        // Comprobar búsqueda con el texto Apple
        $itemSearchData = new ItemSearchData();
        $itemSearchData->setSeachString('Apple');
        $itemSearchData->setLimitCount(1);

        $result = self::$repository->search($itemSearchData);
        $data = $result->getDataAsArray();

        $this->assertCount(1, $data);
        $this->assertEquals(1, $result->getNumRows());
        $this->assertInstanceOf(\stdClass::class, $data[0]);
        $this->assertEquals(2, $data[0]->id);
        $this->assertEquals('Apple', $data[0]->name);
    }

    /**
     * Comprobar las cuentas enlazadas
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetLinked()
    {
        $filter = new QueryCondition();
        $filter->addFilter('Account.parentId = 1');

        $this->assertEquals(1, self::$repository->getLinked($filter)->getNumRows());
    }

    /**
     * Comprobar en incremento del contador de vistas
     *
     * @depends testGetById
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws SPException
     */
    public function testIncrementViewCounter()
    {
        /** @var AccountVData $accountBefore */
        $accountBefore = self::$repository->getById(1)->getData();

        $this->assertTrue(self::$repository->incrementViewCounter(1));

        /** @var AccountVData $accountAfter */
        $accountAfter = self::$repository->getById(1)->getData();

        $this->assertEquals($accountBefore->getCountView() + 1, $accountAfter->getCountView());
    }

    /**
     * Obtener todas las cuentas
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetAll()
    {
        $result = self::$repository->getAll();

        $this->assertEquals(2, $result->getNumRows());

        /** @var AccountData[] $data */
        $data = $result->getDataAsArray();

        $this->assertCount(2, $data);
        $this->assertInstanceOf(AccountData::class, $data[0]);
        $this->assertEquals(1, $data[0]->getId());
        $this->assertInstanceOf(AccountData::class, $data[1]);
        $this->assertEquals(2, $data[1]->getId());
    }

    /**
     * @covers \SP\Repositories\Account\AccountRepository::getPasswordForId()
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function testUpdatePassword()
    {
        $accountRequest = new AccountPasswordRequest();
        $accountRequest->id = 2;
        $accountRequest->key = Crypt::makeSecuredKey(self::SECURE_KEY_PASSWORD);
        $accountRequest->pass = Crypt::encrypt('1234', $accountRequest->key, self::SECURE_KEY_PASSWORD);

        // Comprobar que la modificación de la clave es correcta
        $this->assertTrue(self::$repository->updatePassword($accountRequest));

        $accountPassData = self::$repository->getPasswordForId(2)->getData();
        $clearPassword = Crypt::decrypt($accountPassData->pass, $accountPassData->key, self::SECURE_KEY_PASSWORD);

        // Comprobar que la clave obtenida es igual a la encriptada anteriormente
        $this->assertEquals('1234', $clearPassword);
    }

    /**
     * Comprobar en incremento del contador de desencriptado
     *
     * @depends testGetById
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testIncrementDecryptCounter()
    {
        /** @var AccountVData $accountBefore */
        $accountBefore = self::$repository->getById(1)->getData();

        $this->assertTrue(self::$repository->incrementDecryptCounter(1));

        /** @var AccountVData $accountAfter */
        $accountAfter = self::$repository->getById(1)->getData();

        $this->assertEquals($accountBefore->getCountDecrypt() + 1, $accountAfter->getCountDecrypt());
    }

    /**
     * Comprobar el número total de cuentas
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetTotalNumAccounts()
    {
        $this->assertEquals(7, self::$repository->getTotalNumAccounts()->num);
    }

    /**
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetDataForLink()
    {
        $result = self::$repository->getDataForLink(1);

        $this->assertEquals(1, $result->getNumRows());

        $data = $result->getData();

        $this->assertEquals(1, $data->getId());
        $this->assertEquals(1, $data->getId());
        $this->assertEquals('Google', $data->getName());
        $this->assertEquals('admin', $data->getLogin());
        $this->assertEquals(pack('H*', '6465663530323030656135663361636362366237656462653536343938666234313231616635323237363539663162346532383963386361346565323732656530636238333632316436393736353665373631393435623033353236616164333730336662306531333535626437333638653033666137623565633364306365323634663863643436393436633365353234316534373338376130393133663935303736396364613365313234643432306636393834386434613262316231306138'), $data->getPass());
        $this->assertEquals(pack('H*', '6465663130303030646566353032303065646434636466636231333437613739616166313734343462343839626362643364353664376664356562373233363235653130316261666432323539343633336664626639326630613135373461653562613562323535353230393236353237623863633534313862653363376361376536366139356366353366356162663031623064343236613234336162643533643837643239636633643165326532663732626664396433366133653061343534656664373134633661366237616338363966636263366435303166613964316338386365623264303861333438626633656638653135356538633865353838623938636465653061306463313835646636366535393138393831653366303464323139386236383738333539616563653034376434643637663835313235636661313237633138373865643530616630393434613934616363356265316130323566623065633362663831613933626365366365343734336164363562656638353131343466343332323837356438323339303236656363613866643862376330396563356465373233666466313636656166386336356539666537353436333535333664393766383366316366663931396530386339373730636166633136376661656364306366656262323931666334343831333238333662366432'), $data->getKey());
        $this->assertEquals('http://google.com', $data->getUrl());
        $this->assertEquals('aaaa', $data->getNotes());
        $this->assertEquals('Google', $data->getClientName());
        $this->assertEquals('Web', $data->getCategoryName());

        $this->assertEquals(0, self::$repository->getDataForLink(10)->getNumRows());
    }

    /**
     * Comprobar las cuentas devueltas para un filtro de usuario
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetForUser()
    {
        $queryCondition = new QueryCondition();
        $queryCondition->addFilter('Account.isPrivate = 1');

        $this->assertCount(0, self::$repository->getForUser($queryCondition)->getDataAsArray());
    }

    /**
     * Comprobar las cuentas devueltas para obtener los datos de las claves
     *
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetAccountsPassData()
    {
        $this->assertCount(2, self::$repository->getAccountsPassData());
    }

    /**
     * Comprobar la creación de una cuenta
     *
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testCreate()
    {
        $accountRequest = new AccountRequest();
        $accountRequest->name = 'Prueba 2';
        $accountRequest->login = 'admin';
        $accountRequest->url = 'http://syspass.org';
        $accountRequest->notes = 'notas';
        $accountRequest->userEditId = 1;
        $accountRequest->passDateChange = time() + 3600;
        $accountRequest->clientId = 1;
        $accountRequest->categoryId = 1;
        $accountRequest->isPrivate = 0;
        $accountRequest->isPrivateGroup = 0;
        $accountRequest->parentId = 0;
        $accountRequest->userId = 1;
        $accountRequest->userGroupId = 2;
        $accountRequest->key = Crypt::makeSecuredKey(self::SECURE_KEY_PASSWORD);
        $accountRequest->pass = Crypt::encrypt('1234', $accountRequest->key, self::SECURE_KEY_PASSWORD);

        // Comprobar registros iniciales
        $this->assertEquals(2, $this->conn->getRowCount('Account'));

        self::$repository->create($accountRequest);

        // Comprobar registros finales
        $this->assertEquals(3, $this->conn->getRowCount('Account'));
    }

    /**
     * No implementado
     */
    public function testGetByIdBatch()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * No implementado
     */
    public function testCheckDuplicatedOnUpdate()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * No implementado
     */
    public function testGetPasswordHistoryForId()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * Comprobar la búsqueda de cuentas mediante filtros
     *
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function testGetByFilter()
    {
        $searchFilter = new AccountSearchFilter();
        $searchFilter->setLimitCount(10);
        $searchFilter->setCategoryId(1);

        // Comprobar un Id de categoría
        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(1, $response->getCount());
        $this->assertCount(1, $response->getData());

        // Comprobar un Id de categoría no existente
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setCategoryId(10);

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(0, $response->getCount());
        $this->assertCount(0, $response->getData());

        // Comprobar un Id de cliente
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setClientId(1);

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(1, $response->getCount());
        $this->assertCount(1, $response->getData());

        // Comprobar un Id de cliente no existente
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setClientId(10);

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(0, $response->getCount());
        $this->assertCount(0, $response->getData());

        // Comprobar una cadena de texto
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setCleanTxtSearch('apple.com');

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(1, $response->getCount());
        $this->assertCount(1, $response->getData());
        $this->assertEquals(2, $response->getData()[0]->getId());

        // Comprobar los favoritos
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setSearchFavorites(true);

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(0, $response->getCount());
        $this->assertCount(0, $response->getData());

        // Comprobar las etiquetas
        $searchFilter->reset();
        $searchFilter->setLimitCount(10);
        $searchFilter->setTagsId([1]);

        $response = self::$repository->getByFilter($searchFilter);

        $this->assertInstanceOf(AccountSearchResponse::class, $response);
        $this->assertEquals(1, $response->getCount());
        $this->assertCount(1, $response->getData());
        $this->assertEquals(1, $response->getData()[0]->getId());
    }
}
