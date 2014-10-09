<?php

namespace Topxia\Service\User\Dao;

interface UserRelationDao
{
	public function getUserRelation($id);

	public function addUserRelation($userRelation);

	public function findUserRelationsByToIdsAndType(array $toIds,$type);

	public function findUserRelationsByFromIdAndType($fromId,$type);

	public function findUserRelationsByFromIdsAndType(array $fromIds,$type);

	public function deleteUserRelationsByFromIdAndType($fromId,$type);

}