<?php

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