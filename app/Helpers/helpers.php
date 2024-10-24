<?php

use App\Models\Import;

if (!function_exists('flattenArray')) {
    /**
     * Убирает вложенность из массива после первого элемента
     *
     * @param array $array Входящий массив с вложенностью
     * @return array "Плоский" массив
     */
    function flattenArray(array $array): array
    {
        $flattenedResults = [];

        foreach ($array as $key => $value) {
            if (isset($value[0]) && is_array($value[0])) {
                /** Распаковываем первый элемент вложенного массива */
                $flattenedResults[$key] = $value[0];
            } else {
                /** Если вложенности нет, просто сохраняем значение */
                $flattenedResults[$key] = $value;
            }
        }

        return $flattenedResults;
    }
}

if (!function_exists('logImport')) {
    /**
     * @param  string  $uuid
     * @param  array  $newData
     *
     * @return void
     */
    function logImport(string $uuid, array $newData): void {
        $import = Import::firstOrNew(['uuid' => $uuid]);
        $currentEvents = $import->events_history ?? [];
        $newEvents = is_array($newData['events_history']) ? $newData['events_history'] : [$newData['events_history']];
        $updatedEvents = array_merge($currentEvents, $newEvents);
        $import->events_history = $updatedEvents;
        unset($newData['events_history']);
        $import->fill($newData);
        $import->save();
    }
}