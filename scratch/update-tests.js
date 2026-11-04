const fs = require('fs');
let content = fs.readFileSync('tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', 'utf8');
const isCrlf = content.includes('\r\n');
const nl = isCrlf ? '\r\n' : '\n';

// Replace $room->updated_at->valueOf() with ($room->revision ?? 0)
content = content.split('(string) $room->updated_at->valueOf()').join('(int) ($room->revision ?? 0)');
content = content.split('(string) $existingRoom->updated_at->valueOf()').join('(int) ($existingRoom->revision ?? 0)');
content = content.split('(string) $good->updated_at->valueOf()').join('(int) ($good->revision ?? 0)');
content = content.split('$room->updated_at->valueOf() - 5000').join('99999');
content = content.split('$currentRevision = (string) $room->updated_at->valueOf();').join('$currentRevision = (int) ($room->revision ?? 0);');
content = content.split('$initialRevision = (string) $room->updated_at->valueOf();').join('$initialRevision = (int) ($room->revision ?? 0);');

// Add the dedicated same-second save test if not present
const testName = 'test_two_saves_in_same_second_using_same_expected_revision_fails_second_with_409';
if (!content.includes(testName)) {
  const newTestMethod = [
    '',
    `    public function ${testName}(): void`,
    '    {',
    '        $room = RoomAreaRoom::create([',
    '            \'room_area_project_id\' => $this->project->id,',
    '            \'name\' => \'Initial Room\',',
    '            \'vertices\' => $this->sampleOpenPlan(10.0, 10.0),',
    '            \'area_sqft\' => 100.0,',
    '            \'wall_height_ft\' => 8.0,',
    '            \'revision\' => 0,',
    '        ]);',
    '',
    '        $expectedRevision = (int) ($room->revision ?? 0);',
    '',
    '        // Tab 1 saves in this second using expected_revision = 0 -> succeeds',
    '        $res1 = $this->actingAs($this->user)->putJson(',
    '            route(\'admin.utilities.room-area.rooms.update\', $room),',
    '            [',
    '                \'name\' => \'Tab 1 Updated Room\',',
    '                \'plan\' => $this->sampleOpenPlan(12.0, 12.0),',
    '                \'expected_revision\' => $expectedRevision,',
    '            ]',
    '        );',
    '        $res1->assertOk();',
    '        $res1->assertJsonPath(\'ok\', true);',
    '        $res1->assertJsonPath(\'room.revision\', 1);',
    '',
    '        // Tab 2 attempts to save in the same second using the same expected revision = 0 -> gets 409',
    '        $res2 = $this->actingAs($this->user)->putJson(',
    '            route(\'admin.utilities.room-area.rooms.update\', $room),',
    '            [',
    '                \'name\' => \'Tab 2 Attempted Room\',',
    '                \'plan\' => $this->sampleOpenPlan(14.0, 14.0),',
    '                \'expected_revision\' => $expectedRevision,',
    '            ]',
    '        );',
    '        $res2->assertStatus(409);',
    '        $res2->assertJsonPath(\'ok\', false);',
    '        $res2->assertJsonPath(\'code\', \'conflict\');',
    '    }',
  ].join(nl);

  const lastBrace = content.lastIndexOf('}');
  content = content.slice(0, lastBrace) + newTestMethod + nl + '}' + nl;
}

fs.writeFileSync('tests/Feature/Admin/RoomAreaOpenPlan3DSaveTest.php', content);
console.log('Updated RoomAreaOpenPlan3DSaveTest.php.');
