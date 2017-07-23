<?php
namespace ChatFlow;

interface StateRepositoryInterface
{
	public function getStateData(int $userId): ?array;

    public function saveStateData(int $userId, array $data): void;
}
