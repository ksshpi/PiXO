<?php

require_once('types/Gamer.php');
require_once('types/Cell.php');

class PiXO {

    const SIDE_X = 'X';
    const SIDE_O = 'O';

    function __construct($db) {
        $this->db = $db;
    }

    private function createField() {
        return array(
            array(
                new Cell(1, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(2, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(3, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(4, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(5, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(6, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            ),
            array(
                new Cell(7, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(8, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0))), 
                new Cell(9, array(array(0, 0, 0), array(0, 0, 0), array(0, 0, 0)))
            )
        );
    }    

    public function updateGame($userId,$game) {
        return $this->db->updateGame($userId,$game);
    }

    public function createGame($partyId) {
        // создать пустое игровое поле
        $field = serialize($this->createField()); // перевести его в строку
        $hash = md5($field); // получить хеш-сумму от этого поля
        $this->db->updateGame($partyId, $field, $hash); // записать в БД
    }

    public function getGame($party, $hash) {
        if ($party->hash != $hash) { 
            return array(
                'field' => unserialize($party->game),
                'hash' => $party->hash,
                'turn' => $party->turn,
                'gamers' => array(
                    'gamer1' => $this->db->getGamerById($party->user1_id),
                    'gamer2' => $this->db->getGamerById($party->user2_id)
                )
            );
        }
        return false;
    }

    // вернуть окончание игры
    public function getEndGame($party) {
        if ($party->winner_id) {
            $loserId = ($party->winner_id === $party->user1_id) ? $party->user2_id : $party->user1_id;
            return array(
                'endGame' => true,
                'status' => 'Победа!',
                'winner' => $this->db->getGamerById($party->winner_id)->login,
                'loser' => $this->db->getGamerById($party->loserId)->login
            );
        }
        return array(
            'endGame' => true,
            'status' => 'Ничья!'
        );
    }

    private function checkEmptyCells($cell) {
        for($i = 0; $i <= 2; $i++)
            for($j = 0; $j <= 2; $j++)
                if(!$cell[$i][$j]) return true;
        return false;
    }

    private function isSameValues($cell1, $cell2, $cell3) {
        return !!($cell1 === $cell2 && $cell2 === $cell3 && $cell1 === $cell3);
    }

    private function checkCell($cell, $y, $x, $value) {
        // сравнение значений в столбце
        if ($this->isSameValues($cell->field[$y][0], 
                                $cell->field[$y][1], 
                                $cell->field[$y][2])
        ) {
            return $value;
        }
        // сравнение значений в строке
        if ($this->isSameValues($cell->field[0][$x], 
                                $cell->field[1][$x], 
                                $cell->field[2][$x])
        ) {
            return $value;
        }
        // сравнение диагоналей
        // главная диагональ
        if ($y == $x) {
            if ($this->isSameValues($cell->field[0][0], 
                                    $cell->field[1][1], 
                                    $cell->field[2][2])
            ) {
                return $value;
            }
        }
        // побочная диагональ
        if ($y + $x == 2) {
            if ($this->isSameValues($cell->field[0][2], 
                                    $cell->field[1][1], 
                                    $cell->field[2][0])
            ) { 
                return $value;
            }
        }
        if (!$this->checkEmptyCells($cell->field)) {
            return 'draw';
        }
        return null;
    }

    private function checkGame($feild, $r1, $r2, $result) {
        /*
        if($field[$r1][0]->result == $field[$r1][1]->result == $field[$r1][2]->result) 
            return $result;

        if($field[0][$r2]->result == $field[1][$r2]->result == $field[2][$r2]->result) 
            return $result;
        
        if($r1 == $r2)
            if($field[0][0]->result == $field[1][1]->result == $field[2][2]->result) 
                return $result;
               
        if($r1 + $r2 == 2)
            if($field[0][0]->result == $field[1][1]->result == $field[2][2]->result) 
                return $result;
                */
        return null;
    }

    // игрок сходил как-то
    public function turn($party, $user, $x, $y) {
        if ($user->id === $party->turn) { // проверить, что ход игрока
            $field = unserialize($party->game);
            $r1 = floor($y/3);
            $r2 = floor($x/3);
            $r3 = floor($y - 3 * $r1);
            $r4 = floor($x - 3 * $r2);
            if (!$field[$r1][$r2]->result) { // проверить, что в малый квадрат можно ходить
                // проверить, что в ячейке ещё ничего нет
                if ($field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_X && 
                    $field[$r1][$r2]->field[$r3][$r4] !== $this::SIDE_O
                ) {
                    // совершить ход
                    $value = ($party->user1_id === $user->id) ? $this::SIDE_X : $this::SIDE_O;
                    $field[$r1][$r2]->field[$r3][$r4] = $value;
                    // проверить на победу в ячейке
                    $field[$r1][$r2]->result = $this->checkCell($field[$r1][$r2], $r3, $r4, $value);
                    // проверить на победу в игре
                    //...
                    // записать данные в БД
                    $fieldStr = serialize($field); // перевести его в строку
                    $hash = md5($fieldStr); // получить хеш-сумму от этого поля
                    $this->db->updateGame($party->id, $fieldStr, $hash); // записать в БД
                    // поменять turn партии
                    ($user->id == $party->user1_id) ? $this->db->updateTurn($party->id, $party->user2_id) : 
                                                      $this->db->updateTurn($party->id, $party->user1_id);

                    // Если игра ведется с ИИ
                    $party = $this->db->getPartyByUserId($user->id, 'game');
                    // Проверка на то, играет ли игрок с ИИ ***********************************************************************************************************           
                    if ($party->turn == -1) {
                        // Получаем координаты, куда сходил ИИ
                        $coordinate = $this->aiTurn($field);
                        $r1 = $coordinate['r1'];
                        $r2 = $coordinate['r2'];
                        $r3 = $coordinate['r3'];
                        $r4 = $coordinate['r4'];
                        $field[$r1][$r2]->field[$r3][$r4] = $this::SIDE_O;
                        $field[$r1][$r2]->result = $this->checkCell($field[$r1][$r2], $r3, $r4, $this::SIDE_O);
                        $fieldStr = serialize($field);
                        $hash = md5($fieldStr); 
                        $this->db->updateGame($party->id, $fieldStr, $hash);
                        $this->db->updateTurn($party->id, $party->user1_id);
                        
                    }
                    return true;
                }
            }

        }
        return false;
    }

    // Эмитируем ход ИИ
    public function aiTurn($field) {
        // Создаем копию сетки полей, где будет ходить ИИ
        $memoField = $field;
        for ($i = 0; $i < count($memoField); $i++) {
            for ($j = 0; $j < count($memoField[$i]); $j++) {
                // Копия поля
                $cell = $memoField[$i][$j];
                // Проверка на победу в поле
                if (!$cell->result) {
                    $cellField = $cell->field;
                    // Проверяем, ходил ИИ на этом поле
                    if ($this->checkFirstAiTurn($cellField)) {
                        // Если да, то ставим О в случайное положение
                        $r3 = rand(0, 2);
                        $r4 = rand(0, 2);
                        $ok = true;
                        // Цикл для того, что бы получить координаты пустого положения
                        while ($ok) {
                            if ($cellField[$r3][$r4] === $this::SIDE_X) {
                                $r3 = rand(0, 2);
                                $r4 = rand(0, 2);
                            } else {
                                $ok = false;
                            }
                        }
                        // ВОзвращаем ассоциативным массивом 
                        return ["r1" => $i, "r2" => $j, "r3" => $r3, "r4" => $r4];
                    // Если нет, то включаем МОЗГ ИИ. ИИ будет "думать" куда ходить, что бы победить
                    } else {
                        // Берем "победные" координаты
                        $coordinate = $this->aiMathToVictory($cellField);
                        if ($coordinate) {     
                            return ["r1" => $i, "r2" => $j, "r3" => $coordinate[0], "r4" => $coordinate[1]];
                        }
                    }
                }
            }
        }
        return null;
    }

    // Проверка, ходил ли ИИ на этом поле ***************************
    public function checkFirstAiTurn($field) {
        for ($i = 0; $i < count($field); $i++) {
            for ($j = 0; $j < count($field[$i]); $j++) {
                if ($field[$i][$j] === $this::SIDE_O) {
                    return false;
                }
            }
        }
        return true;
    }

    // Считать "победные" координаты *********************************************************************
    public function aiMathToVictory($field) {

        // Думает на один ход вперед
        for ($i = 0; $i < count($field); $i++) {
            for ($j = 0; $j < count($field[$i]); $j++) {
                // Копия поля
                $memoField = $field;
                // Если место пустое
                if ($memoField[$i][$j] === 0) {
                    $memoField[$i][$j] = $this::SIDE_O;
                    // Проверка, победит ли ИИ в этих координатах
                    if ($this->checkField($memoField, $i, $j, $this::SIDE_O)) {
                        return array($i, $j);
                    }
                }
            }
        }

        // Думает на два хода вперед 
        for ($i = 0; $i < count($field); $i++) {
            for ($j = 0; $j < count($field[$i]); $j++) {
                // Копия поля
                $memoField = $field;
                // Если место пустое
                if ($memoField[$i][$j] === 0) {
                    $memoField[$i][$j] = $this::SIDE_O;
                    for ($k = 0; $k < count($field); $k++) {
                        for ($l = 0; $l < count($field[$k]); $l++) {
                            // Копия поля, где ИИ уже сходил
                            $memoField_ = $memoField;
                            // Если место пустое
                            if ($memoField_[$k][$l] === 0) {
                                $memoField_[$k][$l] = $this::SIDE_O;
                                // Проверка, победит ли ИИ в этомх координатах
                                if ($this->checkField($memoField_, $k, $l, $this::SIDE_O)) {
                                    return array($i, $j);
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;

    }

    // Измененная функция checkCell. Теперь проверяет лишь самое поле, а не все Cell/ *****************************************************
    private function checkField($field, $y, $x, $value) {
        // сравнение значений в столбце
        if ($this->isSameValues($field[$y][0], 
                                $field[$y][1], 
                                $field[$y][2])
        ) {
            return $value;
        }
        // сравнение значений в строке
        if ($this->isSameValues($field[0][$x], 
                                $field[1][$x], 
                                $field[2][$x])
        ) {
            return $value;
        }
        // сравнение диагоналей
        // главная диагональ
        if ($y == $x) {
            if ($this->isSameValues($field[0][0], 
                                    $field[1][1], 
                                    $field[2][2])
            ) {
                return $value;
            }
        }
        // побочная диагональ
        if ($y + $x == 2) {
            if ($this->isSameValues($field[0][2], 
                                    $field[1][1], 
                                    $field[2][0])
            ) { 
                return $value;
            }
        }
        return null;
    }

}
