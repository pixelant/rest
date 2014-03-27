<?php
/*
 *  Copyright notice
 *
 *  (c) 2014 Daniel Corn <info@cundd.net>, cundd
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

namespace Cundd\Rest\VirtualObject\Persistence;


use Cundd\Rest\ObjectManager;
use Cundd\Rest\VirtualObject\ConfigurationInterface;
use Cundd\Rest\VirtualObject\Exception\MissingConfigurationException;
use Cundd\Rest\VirtualObject\ObjectConverter;
use Cundd\Rest\VirtualObject\VirtualObject;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface as ExtbaseRepositoryInterface;


/**
 * Repository for Virtual Objects
 *
 * @package Cundd\Rest\VirtualObject\Persistence
 */
class Repository implements RepositoryInterface, ExtbaseRepositoryInterface {
	/**
	 * @var \Cundd\Rest\ObjectManager
	 * @inject
	 */
	protected $objectManager;

	/**
	 * The configuration to use when converting
	 *
	 * @var ConfigurationInterface
	 */
	protected $configuration;

	/**
	 * @var \Cundd\Rest\VirtualObject\Persistence\BackendInterface
	 * @inject
	 */
	protected $backend;

	/**
	 * @var ObjectConverter
	 */
	protected $objectConverter;

	/**
	 * Registers the given Virtual Object
	 *
	 * This is a high level shorthand for:
	 * Object exists?
	 *    Yes -> update
	 *    No -> add
	 *
	 * @param VirtualObject $object
	 * @return VirtualObject Returns the registered Document
	 */
	public function registerObject($object) {
		$identifierQuery = $this->getIdentifierColumnsOfObject($object);
		if (
			$identifierQuery
			&& $this->backend->getObjectCountByQuery($this->getSourceIdentifier(), $identifierQuery)
		) {
			$this->update($object);
		} else {
			$this->add($object);
		}
		return $object;
	}

	/**
	 * Adds the given object to the database
	 *
	 * @param VirtualObject $object
	 * @return void
	 */
	public function add($object) {
		$this->backend->addRow(
			$this->getSourceIdentifier(),
			$this->getObjectConverter()->convertFromVirtualObject($object)
		);
	}

	/**
	 * Updates the given object in the database
	 *
	 * @param VirtualObject $object
	 * @return void
	 */
	public function update($object) {
		$identifierQuery = $this->getIdentifierColumnsOfObject($object);
		if (
			$identifierQuery
			&& $this->backend->getObjectCountByQuery($this->getSourceIdentifier(), $identifierQuery)
		) {
			$this->backend->updateRow(
				$this->getSourceIdentifier(),
				$identifierQuery,
				$this->getObjectConverter()->convertFromVirtualObject($object)
			);
		}
	}

	/**
	 * Removes the given object from the database
	 *
	 * @param VirtualObject $object
	 * @return void
	 */
	public function remove($object) {
		$identifierQuery = $this->getIdentifierColumnsOfObject($object);
		if (
			$identifierQuery
			&& $this->backend->getObjectCountByQuery($this->getSourceIdentifier(), $identifierQuery)
		) {
			$this->backend->removeRow(
				$this->getSourceIdentifier(),
				$identifierQuery,
				$this->getObjectConverter()->convertFromVirtualObject($object)
			);
		}
	}

	/**
	 * Returns all objects from the database
	 *
	 * @return array
	 */
	public function findAll() {
		$objectConverter = $this->getObjectConverter();
		$objectCollection = array();

		$rawObjectCollection = $this->createQuery()
			->setSourceIdentifier($this->getSourceIdentifier())
			->execute()
		;

		foreach ($rawObjectCollection as $rawObjectData) {
			$objectCollection[] = $objectConverter->convertToVirtualObject($rawObjectData);
		}
		return $objectCollection;
	}

	/**
	 * Returns the total number objects of this repository.
	 *
	 * @return integer The object count
	 * @api
	 */
	public function countAll() {
		return $this->backend->getObjectCountByQuery($this->getSourceIdentifier(), array());
	}

	/**
	 * Removes all objects of this repository as if remove() was called for
	 * all of them.
	 *
	 * @return void
	 * @api
	 */
	public function removeAll() {
		foreach ($this->findAll() as $object) {
			$this->remove($object);
		}
	}

	/**
	 * Returns the object with the given identifier
	 *
	 * @param string $identifier
	 * @return VirtualObject
	 */
	public function findByIdentifier($identifier) {
		$configuration = $this->getConfiguration();

		$identifierProperty = $configuration->getIdentifier();
		$identifierKey = $configuration->getSourceKeyForProperty($identifierProperty);


		$objectConverter = $this->getObjectConverter();
		$query = array(
			$identifierKey => $identifier
		);

		$rawObjectCollection = $this->createQuery()
			->setSourceIdentifier($this->getSourceIdentifier())
			->setLimit(1)
			->setConstraint($query)
			->execute()
		;
		#$rawObjectCollection = $this->backend->getObjectDataByQuery($this->getSourceIdentifier(), $query);
		foreach ($rawObjectCollection as $rawObjectData) {
			return $objectConverter->convertToVirtualObject($rawObjectData);
		}
		return NULL;
	}

	/**
	 * Returns the array of identifier properties of the object
	 *
	 * @param object $object
	 * @return array
	 */
	public function getIdentifiersOfObject($object) {
		$objectData = $object->getData();
		$identifier = $this->getConfiguration()->getIdentifier();
		return isset($objectData[$identifier]) ? array($identifier => $objectData[$identifier]) : array();
	}

	/**
	 * Returns the array of identifier columns and value of the object
	 *
	 * @param object $object
	 * @return array
	 */
	public function getIdentifierColumnsOfObject($object) {
		$configuration = $this->getConfiguration();
		$objectData = $object->getData();
		$identifier = $configuration->getIdentifier();
		$identifierColumn = $configuration->getSourceKeyForProperty($identifier);
		return isset($objectData[$identifier]) ? array($identifierColumn => $objectData[$identifier]) : array();
	}


	/**
	 * Returns the source identifier (the database table name)
	 *
	 * @return string
	 */
	public function getSourceIdentifier() {
		return $this->getConfiguration()->getSourceIdentifier();
	}

	/**
	 * Sets the configuration to use when converting
	 *
	 * @param \Cundd\Rest\VirtualObject\ConfigurationInterface $configuration
	 * @return $this
	 */
	public function setConfiguration($configuration) {
		$this->configuration = $configuration;
		$this->objectConverter = NULL;
		return $this;
	}

	/**
	 * Returns the configuration to use when converting
	 *
	 * @throws \Cundd\Rest\VirtualObject\Exception\MissingConfigurationException if the configuration is not set
	 * @return \Cundd\Rest\VirtualObject\ConfigurationInterface
	 */
	public function getConfiguration() {
		if (!$this->configuration) {
			throw new MissingConfigurationException('Configuration not set', 1395681118);
		}
		return $this->configuration;
	}

	/**
	 * Returns the Object Converter for the current configuration
	 *
	 * @return ObjectConverter
	 */
	protected function getObjectConverter() {
		if (!$this->objectConverter) {
			$this->objectConverter = new ObjectConverter($this->getConfiguration());
		}
		return $this->objectConverter;
	}

	/**
	 * Finds an object matching the given identifier.
	 *
	 * @param integer $uid The identifier of the object to find
	 * @return object The matching object if found, otherwise NULL
	 * @api
	 */
	public function findByUid($uid) {
		return $this->findByIdentifier($uid);
	}

	/**
	 * Sets the property names to order the result by per default.
	 * Expected like this:
	 * array(
	 * 'foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
	 * 'bar' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING
	 * )
	 *
	 * @param array $defaultOrderings The property names to order by
	 * @return void
	 * @api
	 */
	public function setDefaultOrderings(array $defaultOrderings) {
		// TODO: Implement setDefaultOrderings() method
	}

	/**
	 * Sets the default query settings to be used in this repository
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface $defaultQuerySettings The query settings to be used by default
	 * @return void
	 * @api
	 */
	public function setDefaultQuerySettings(\TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface $defaultQuerySettings) {
		// TODO: Implement setDefaultQuerySettings() method.
	}

	/**
	 * Returns a query for objects of this repository
	 *
	 * @return QueryInterface
	 * @api
	 */
	public function createQuery() {
		$query = $this->objectManager->get('Cundd\\Rest\\VirtualObject\\Persistence\\QueryInterface');
		$query->setSourceIdentifier($this->getSourceIdentifier());
		return $query;
	}



}