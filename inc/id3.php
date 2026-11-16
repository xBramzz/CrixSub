<?php
function decodeId3Text($raw, $enc) {
    switch ($enc) {
        case 0:  return trim(@iconv('ISO-8859-1', 'UTF-8//IGNORE', $raw) ?: '');
        case 1:  return trim(@mb_convert_encoding($raw, 'UTF-8', 'UTF-16') ?: '');
        case 2:  return trim(@mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') ?: '');
        default: return trim($raw);
    }
}

function id3SynchSafe($b) {
    return ((ord($b[0]) & 0x7f) << 21) | ((ord($b[1]) & 0x7f) << 14)
         | ((ord($b[2]) & 0x7f) << 7)  | (ord($b[3]) & 0x7f);
}

function parseAudioMetadata($path) {
    $meta = ['title'=>null, 'artist'=>null, 'album'=>null, 'cover'=>null, 'coverMime'=>null];
    $fh = @fopen($path, 'rb');
    if (!$fh) return $meta;

    $header = fread($fh, 10);
    if ($header !== false && strlen($header) === 10 && substr($header, 0, 3) === 'ID3') {
        $ver  = ord($header[3]);
        $size = id3SynchSafe(substr($header, 6, 4));
        $tag  = fread($fh, $size);
        $pos  = 0;
        $len  = strlen($tag);

        while ($pos + 10 <= $len) {
            $id = substr($tag, $pos, 4);
            if (!preg_match('/^[A-Z0-9]{4}$/', $id)) break;

            if ($ver >= 4) {
                $frameSize = id3SynchSafe(substr($tag, $pos + 4, 4));
            } else {
                $u = unpack('N', substr($tag, $pos + 4, 4));
                $frameSize = $u ? $u[1] : 0;
            }
            if ($frameSize <= 0 || $pos + 10 + $frameSize > $len) break;
            $frame = substr($tag, $pos + 10, $frameSize);

            if (in_array($id, ['TIT2', 'TPE1', 'TALB'])) {
                $val = decodeId3Text(substr($frame, 1), ord($frame[0]));
                if ($id === 'TIT2') $meta['title']  = $val;
                if ($id === 'TPE1') $meta['artist'] = $val;
                if ($id === 'TALB') $meta['album']  = $val;
            }
            elseif ($id === 'APIC' && $meta['cover'] === null) {
                $enc  = ord($frame[0]);
                $rest = substr($frame, 1);
                $np   = strpos($rest, "\0");
                if ($np !== false) {
                    $mime = substr($rest, 0, $np);
                    $rest = substr($rest, $np + 1);
                    if (strlen($rest) > 1) {
                        $rest = substr($rest, 1); // picture type byte
                        if ($enc === 1 || $enc === 2) {
                            $end  = strpos($rest, "\0\0");
                            $rest = substr($rest, ($end === false ? 0 : $end) + 2);
                        } else {
                            $end  = strpos($rest, "\0");
                            $rest = substr($rest, ($end === false ? 0 : $end) + 1);
                        }
                        if (strlen($rest) > 0) {
                            $meta['cover']     = $rest;
                            $meta['coverMime'] = $mime ?: 'image/jpeg';
                        }
                    }
                }
            }
            $pos += 10 + $frameSize;
        }
    }

    // ID3v1 fallback
    if (filesize($path) >= 128) {
        fseek($fh, -128, SEEK_END);
        $v1 = fread($fh, 128);
        if ($v1 !== false && substr($v1, 0, 3) === 'TAG') {
            if (!$meta['title'])  $meta['title']  = trim(substr($v1, 3, 30));
            if (!$meta['artist']) $meta['artist'] = trim(substr($v1, 33, 30));
            if (!$meta['album'])  $meta['album']  = trim(substr($v1, 63, 30));
        }
    }
    fclose($fh);
    return $meta;
}

function saveCover($binary, $mime) {
    if (!$binary) return null;
    $dir = __DIR__ . '/../assets/covers/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ext  = (stripos((string)$mime, 'png') !== false) ? 'png' : 'jpg';
    $name = 'cover_' . uniqid() . '.' . $ext;
    file_put_contents($dir . $name, $binary);
    return $name;
}
?>