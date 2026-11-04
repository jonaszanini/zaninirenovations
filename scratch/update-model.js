const fs = require('fs');
let content = fs.readFileSync('app/Models/RoomAreaRoom.php', 'utf8');
const isCrlf = content.includes('\r\n');
const nl = isCrlf ? '\r\n' : '\n';

content = content.replace(
  "'openplan3d_import_meta',",
  "'openplan3d_import_meta'," + nl + "        'revision',"
);
content = content.replace(
  "'openplan3d_import_meta' => 'array',",
  "'openplan3d_import_meta' => 'array'," + nl + "        'revision' => 'integer',"
);

fs.writeFileSync('app/Models/RoomAreaRoom.php', content);
console.log('Updated RoomAreaRoom model.');
