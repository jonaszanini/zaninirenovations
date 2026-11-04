const fs = require('fs');
let content = fs.readFileSync('app/Http/Controllers/Admin/RoomAreaController.php', 'utf8');
const isCrlf = content.includes('\r\n');
const nl = isCrlf ? '\r\n' : '\n';

// 1. In updateRoom, check serverRevision from $room_area_room->revision ?? 0
const oldRevCheck = `$serverRevision = (int) ($room_area_room->updated_at?->valueOf() ?? ($room_area_room->updated_at ? $room_area_room->updated_at->timestamp * 1000 : 0));`;
const newRevCheck = `$serverRevision = (int) ($room_area_room->revision ?? 0);`;
content = content.replace(oldRevCheck, newRevCheck);

const oldExpCheck = `                $expMs = (int) $expRaw;
                if ($serverRevision > $expMs) {`;
const newExpCheck = `                $expInt = (int) $expRaw;
                if ($serverRevision !== $expInt) {`;
content = content.replace(oldExpCheck.replace(/\n/g, nl), newExpCheck.replace(/\n/g, nl));

// 2. Increment revision on update in openplan save
const oldOpenPlanUpdate = `            $room_area_room->update([
                'name' => $data['name'],
                'vertices' => $plan,
                'legacy_canvas_data' => $legacyBackup,`;
const newOpenPlanUpdate = `            $room_area_room->update([
                'name' => $data['name'],
                'vertices' => $plan,
                'legacy_canvas_data' => $legacyBackup,
                'revision' => ((int) ($room_area_room->revision ?? 0)) + 1,`;
content = content.replace(oldOpenPlanUpdate.replace(/\n/g, nl), newOpenPlanUpdate.replace(/\n/g, nl));

// 3. Increment revision on update in standard save
const oldStandardUpdate = `        $room_area_room->update([
            'name' => $data['name'],
            'vertices' => $normalized,`;
const newStandardUpdate = `        $room_area_room->update([
            'name' => $data['name'],
            'vertices' => $normalized,
            'revision' => ((int) ($room_area_room->revision ?? 0)) + 1,`;
content = content.replace(oldStandardUpdate.replace(/\n/g, nl), newStandardUpdate.replace(/\n/g, nl));

// 4. Return integer revision in roomPayload
const oldPayloadRev = `'revision' => (string) ($room->updated_at?->valueOf() ?? ($room->updated_at ? $room->updated_at->timestamp * 1000 : 0)),`;
const newPayloadRev = `'revision' => (int) ($room->revision ?? 0),`;
content = content.replace(oldPayloadRev, newPayloadRev);

fs.writeFileSync('app/Http/Controllers/Admin/RoomAreaController.php', content);
console.log('Updated RoomAreaController.php.');
