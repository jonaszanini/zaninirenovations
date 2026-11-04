const fs = require('fs');
let content = fs.readFileSync('public/js/openplan3d-host.js', 'utf8');

content = content.replace(
  'loadedRoomRevision = room ? (room.revision || (room.updated_at ? String(new Date(room.updated_at).getTime()) : null)) : null;',
  'loadedRoomRevision = room ? (room.revision != null ? room.revision : (room.updated_at ? String(new Date(room.updated_at).getTime()) : null)) : null;'
);
content = content.replace(
  'loadedRoomRevision = updatedRoom.revision || (updatedRoom.updated_at ? String(new Date(updatedRoom.updated_at).getTime()) : null);',
  'loadedRoomRevision = updatedRoom ? (updatedRoom.revision != null ? updatedRoom.revision : (updatedRoom.updated_at ? String(new Date(updatedRoom.updated_at).getTime()) : null)) : null;'
);

fs.writeFileSync('public/js/openplan3d-host.js', content);
console.log('Updated openplan3d-host.js.');
