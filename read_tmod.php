function ReadLeb128($data) {
    $byteValues = array_values(unpack("C*", $data));
    
    $result = 0;
    $shift = 0;
    $pos = 0;
    foreach ($byteValues as $byte) {
        $result |= ($byte & 0x7F) << $shift;
        $pos++;
        if (!($byte & 0x80)) {
            $result &= (1 << 32) - 1;
            $result = (int)$result;
            return array($result, $pos);
        }
        $shift += 7;
        if ($shift >= 64) {
            throw new Exception("Too many bytes when decoding varint.");
        }
    }
}

function gzuncompress_crc32($data) {
     $f = tempnam('/tmp', 'gz_fix');
     file_put_contents($f, "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $data);
     return file_get_contents('compress.zlib://' . $f);
}

function ReadTmod($data) {
    $header_size = unpack("P", substr($data, 0, 8))[1];
    $header = substr($data, 8, $header_size - 8);
    $files_stream = gzuncompress_crc32(substr($data, $header_size));
    $tmod_version = unpack("S", substr($header, 0, 2))[1];
    $header = substr($header, 2);
    $properties_count = unpack("S", substr($header, 0, 2))[1];
    $header = substr($header, 2);
    $properties = array();
    for ($i = 0; $i < $properties_count; $i++) {
        list($name_size, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        $name = substr($header, 0, $name_size);
        $header = substr($header, $name_size);
        list($value_size, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        $value = substr($header, 0, $value_size);
        $header = substr($header, $value_size);
        $properties[$name] = $value;
    }
    $files = array();
    while ($header) {
        $file_name_size = unpack("C", substr($header, 0, 1))[1];
        $header = substr($header, 1);
        $file_name = substr($header, 0, $file_name_size);
        $header = substr($header, $file_name_size);
        list($index, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        list($foffset, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        list($size, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        list($checksum, $offset) = ReadLeb128($header);
        $header = substr($header, $offset);
        $content = substr($files_stream, $foffset, $size);
        $files[$file_name] = array(
            "index" => $index,
            "offset" => $foffset,
            "size" => $size,
            "checksum" => $checksum,
            "content" => $content
        );
    }
    
    return array(
        "header_size" => $header_size,
        "tmod_version" => $tmod_version,
        "properties_count" => $properties_count,
        "properties" => $properties,
        "files" => $files
    );
}