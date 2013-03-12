<?php

/*
 * This file is part of the CMedia Bundle
 *
 * (c) Alexandr Jeliuc <jeliucalexandr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CMedia\Bundle\TagBundle\Assistant;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Doctrine\Common\Collections\ArrayCollection;

use Cmedia\Bundle\TagBundle\Entity\Interfaces\TagContainable;
use Cmedia\Bundle\TagBundle\Entity\Interfaces\Taggable;

/**
 * TagAssistant
 * 
 * @author Alexandr Jeliuc <jeliucalexandr@gmail.com>
 * @package CMedia\Bundle\TagBundle\Assistant
 * @license MIT http://opensource.org/licenses/MIT
 * @copyright Alexandr Jeliuc <jeliucalexandr@gmail.com>
 *
 */
class TagAssistant
{
	/**
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * @var \Cmedia\TagBundle\Entity\Interfaces\Taggable
	 */
	protected $taggable;

	/**
	 * @var string
	 */
	protected $tagEntity;

	/**
	 * @var string
	 */
	protected $delimiter;

	/**
	 * class constructor
	 * 
	 * @param Doctrine $doctrine doctrine service
	 * @param string $tagEntity 
	 * @param string $delimiter
	 * 
	 * @return TagAssistant
	 */
	public function __construct($doctrine, $delimiter = ',')
	{
		$this->em = $doctrine->getEntityManager();
		
		$this->delimiter = $delimiter;
		
		return $this;
	}

	/**
	 * Process submitted tags
	 * 
	 * @api
	 * 
	 * @param Taggable $taggable
	 * 
	 * @return void
	 */
	public function processTags(Taggable $taggable, $tagEntity)
	{

		$this->taggable = $taggable;

		$this->tagEntity = $tagEntity;

		$this->tagArr = $this->tagStringToArray($taggable->getTagsInserted(), $this->delimiter);

		$this->unsetRemovedTags($this->tagArr)
			->setTags($this->loadOrCreateTags($this->tagArr))
		;
	}

	/**
	 * @param array $tagArr
	 * 
	 * @return array[] $tags array of tag entities
	 */
	protected function loadOrCreateTags($tagArr)
	{
		if (empty($tagArr)) {
			return array();
		}
		
		$tagNames = array_unique($tagArr); // get unique name array
		
		$tags = $this->getExistingTags($tagNames); // get existing tag entities collection
		
		$missingTagNames = $this->getMissingTagNames($tagNames, $tags); // calculate missing in db tag names

		$missingTags = $this->createMissingTags($missingTagNames); // create tags for missing names

		$tags = $this->addMissingTags($tags, $missingTags); // and add missing tag names in collection

		return $tags;
	}

	/**
	 * @param array $currentTagNames 
	 * 
	 * @return TagAssistant
	 */
	protected function unsetRemovedTags($currentTagNames)
	{

		$oldTags = $this->taggable->getTags(); // get old tags

		$oldTagNames = $this->getOldTagNames($oldTags); // get names of old tags

		$tagNamesToBeRemoved = array_diff($oldTagNames, $currentTagNames); // get names of tags to remove

		$removedTagsPosition = $this->getRemovedTagsPosition($tagNamesToBeRemoved, $oldTagNames); // get position of removed tags

		foreach ($removedTagsPosition as $position) {
			$this->taggable->removeTag(
				$oldTags[$position]
			);
		}

		return $this;
	}

	/**
	 * @param array $tags
	 */
	protected function setTags($tags)
	{
		if(empty($tags)) {
			return;
		}

		$this->taggable->setTags(new ArrayCollection($tags));

	}

	/**
	 * @param array $oldTags
	 */
	protected function getOldTagNames($oldTags)
	{
		$oldTagNames = array();

		foreach($oldTags as $oldTag)
		{
			$oldTagNames[] = $oldTag->getName();
		}

		return $oldTagNames;
	}

	/**
	 * @param array $tagNameToBeRemoved
	 * @param ArrayCollection $oldTags 
	 * 
	 * @return array
	 */
	protected function getRemovedTagsPosition(array $tagNamesToBeRemoved, $oldTags)
	{
		$removedPositions = array();

		foreach ($oldTags as $key => $oldTag) {
			if(in_array($oldTag, $tagNamesToBeRemoved)) {
				$removedPositions[] = $key;
			}
		}

		return $removedPositions;
	}

	/**
	 * @param array $tagNames
	 * 
	 * @return array $existingTagNames
	 */
	protected function getExistingTags(array $tagNames)
	{
		$builder = $this->em->createQueryBuilder();
		$existingTagNames = $builder
			->select('t')
			->from($this->tagEntity, 't')
			->where($builder->expr()->in('t.name', $tagNames))
			->getQuery()
			->getResult()
		;

		return $existingTagNames;
	}

	/**
	 * @param array $tagNames
	 * @param array $tags
	 */
	protected function getMissingTagNames(array $tagNames, $tags)
	{
		$loadedNames = array();
		foreach ($tags as $tag) {
			$loadedNames[] = $tag->getName();
		}

		$missingNames = array_udiff($tagNames, $loadedNames, 'strcasecmp');

		return $missingNames;
	}

	/**
	 * @param array $missingTagNames
	 * 
	 * @return array
	 */
	protected function createMissingTags($missingTagNames)
	{
		$missingTags = array();

		if (sizeof($missingTagNames)) {
			foreach ($missingTagNames as $tagName) {
				$newTag = new $this->tagEntity();
				$newTag->setName($tagName);
				$missingTags[] = $newTag;
			}
		}

		return $missingTags;
	}

	/**
	 * @param array $tags
	 * @param array $missingTags
	 * 
	 * @return array $tags
	 */
	protected function addMissingTags($tags, $missingTags)
	{
		foreach ($missingTags as $missingTag) {
			$tags[] = $missingTag;
		}

		return $tags;
	}

	/**
	 * @param string $tagString
	 * @param string $delimiter
	 * 
	 * @return array
	 */
	protected function tagStringToArray($tagString, $delimiter)
	{
		$tags = explode($delimiter, $tagString);
		$tags = array_map('trim', $tags);
		$tags = array_filter($tags, function($value) { return !empty($value); });

		return $this->tags = array_values($tags);
	}

	/**
	 * This method is static helper to use inside entity
	 * @api
	 * 
	 * @example TagAssistant::tagArrayToString($this); // will return something like "tag,another tag,new tag" 
	 * 
	 * @param Taggable $taggable
	 * @param string $delimiter
	 * 
	 * @return string	 
	 */
	public static function tagArrayToString(Taggable $taggable, $delimiter = ',')
	{

		$tags = $taggable->getTags();

		if(empty($tags)) {
			return '';
		}

		$tagString = '';

		foreach($tags as $tag)
		{
			$tagString .= $tag->getName() . $delimiter;
		}

		return $tagString;
	}
}