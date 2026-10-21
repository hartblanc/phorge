<?php

final class DifferentialChangesetParserTestCase extends PhabricatorTestCase {

  public function testDiffChangesets() {
    $hunk = new DifferentialHunk();
    $hunk->setChanges("+a\n b\n-c\n");
    $hunk->setNewOffset(1);
    $hunk->setNewLen(2);
    $left = new DifferentialChangeset();
    $left->attachHunks(array($hunk));

    $tests = array(
      "+a\n b\n-c\n" => array(array(), array()),
      "+a\n x\n-c\n" => array(array(), array()),
      "+aa\n b\n-c\n" => array(array(1), array(11)),
      " b\n-c\n" => array(array(1), array()),
      "+a\n b\n c\n" => array(array(), array(13)),
      "+a\n x\n c\n" => array(array(), array(13)),
    );

    foreach ($tests as $changes => $expected) {
      $hunk = new DifferentialHunk();
      $hunk->setChanges($changes);
      $hunk->setNewOffset(11);
      $hunk->setNewLen(3);
      $right = new DifferentialChangeset();
      $right->attachHunks(array($hunk));

      $parser = new DifferentialChangesetParser();
      $parser->setOriginals($left, $right);
      $this->assertEqual($expected, $parser->diffOriginals(), $changes);
    }
  }

  public function testIsOneSidedChangeset() {
    $type_add = DifferentialChangeType::TYPE_ADD;
    $type_change = DifferentialChangeType::TYPE_CHANGE;
    $type_delete = DifferentialChangeType::TYPE_DELETE;
    $type_move = DifferentialChangeType::TYPE_MOVE_HERE;

    $tests = array(
      'added file' => array($type_add, array("+a\n+b\n"), true),
      'deleted file' => array($type_delete, array("-a\n-b\n"), true),
      'added binary file' => array($type_add, array(), true),
      'deleted binary file' => array($type_delete, array(), true),
      'only adds lines' => array($type_change, array(" a\n+b\n c\n"), true),
      'only removes lines' => array($type_change, array(" a\n-b\n c\n"), true),
      'adds and removes lines' => array(
        $type_change,
        array(" a\n-b\n+c\n"),
        false,
      ),
      'adds and removes lines in different hunks' => array(
        $type_change,
        array(" a\n+b\n", " c\n-d\n"),
        false,
      ),
      'modified binary file' => array($type_change, array(), false),
      'moved file with no changes' => array($type_move, array(), false),
      'moved file which only adds lines' => array(
        $type_move,
        array(" a\n+b\n"),
        true,
      ),
      'moved file which adds and removes lines' => array(
        $type_move,
        array(" a\n-b\n+c\n"),
        false,
      ),
    );

    foreach ($tests as $name => $test) {
      list($change_type, $changes, $expected) = $test;

      $hunks = array();
      foreach ($changes as $change) {
        $hunk = new DifferentialHunk();
        $hunk->setChanges($change);
        $hunk->setOldOffset(1);
        $hunk->setNewOffset(1);
        $hunks[] = $hunk;
      }

      $changeset = id(new DifferentialChangeset())
        ->setChangeType($change_type)
        ->attachHunks($hunks);

      $this->assertEqual(
        $expected,
        DifferentialChangesetParser::isOneSidedChangeset($changeset),
        $name);
    }
  }

}
