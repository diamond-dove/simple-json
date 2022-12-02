<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;

class JsonDb
{
    public $file;
    private $fp;
    protected string $dir;
    private string $load;

    public function __construct( $dir) {
        $this->dir = $dir;
    }

    public function check_fp_size(): int
    {
        $size = 0;

        if ( $this->fp ) {
            $cur_size = ftell( $this->fp );
            fseek( $this->fp, 0, SEEK_END );
            $size = ftell( $this->fp );
            fseek( $this->fp, $cur_size, SEEK_SET );
        }

        return $size;
    }

    public function from($file, $load = 'full'): self
    {
        $this->file = sprintf( '%s/%s.json', $this->dir, str_replace('.json', '', $file)); // Adding .json extension is no longer necessary
        $this->load = $load;

        return $this;
    }

    public function get(): \Closure
    {
        return function () {
            if (!file_exists($this->file)) {
                touch($this->file);
            }

            if ( 'partial' == $this->load ) {
                $this->fp = fopen( $this->file, 'r+' );
                if (!$this->fp) {
                    throw new \Exception( 'Unable to open json file' );
                }

                if (($size = $this->check_fp_size())) {
                    $content = $this->get_json_chunk($this->fp);

                    // We could not get the first chunk of JSON. Lets try to load everything then
                    if (!$content) {
                        $content = fread($this->fp, $size);
                    } else {
                        // We got the first chunk, we still need to put it into an array
                        $content = sprintf( '[%s]', $content );
                    }

                    $content = json_decode($content, true);

                    // Check if its arrays of jSON
                    if(!is_array($content) && is_object($content)) {
                        yield $content;
                    }

                    if(!is_array($content) && !is_object($content) ) {
                        throw new InvalidJsonException( 'json is invalid' );
                    }

                    foreach ($content as $row) {
                        yield $row;
                    }
                }
            }

            $content = file_get_contents($this->file);
            $content = json_decode($content, true);

            // Check if its arrays of jSON
            if(!is_array($content) && is_object($content)) {
                yield $content;
            }

            if(!is_array($content) && !is_object($content) ) {
                throw new InvalidJsonException( 'json is invalid' );
            }

            foreach ($content as $row) {
                yield $row;
            }
        };
    }

    private function get_json_chunk($fp, int $start_depth = -1 ) {
        $bufsz = 8192;
        $start = false;
        $quotes = [false, false];
        $depth = 0;
        $total_bytes_read = 0;
        $end = false;

        if (!$fp) {
            return;
        }

        $cur_pos = ftell( $fp );
        // Get total size of file
        fseek( $fp, 0, SEEK_END );
        $size = ftell( $fp );
        rewind( $fp );

        while ( ! feof( $fp ) ) {
            $buffer = fread( $fp, $bufsz );
            $i = 0;

            if ( false !== $buffer ) {
                // So I do not have to do strlen to get read size which is linear
                $read_count = ( $size > $bufsz ) ? $bufsz : $size;
                $size -= $read_count;

                if ( false === $start ) {
                    // Find first occurence of the curly bracket
                    $start = strpos( $buffer, '{' );
                    if ( false === $start ) {
                        $total_bytes_read += $read_count;
                        continue;
                    } else {
                        $i = $start+1;
                        $start += $total_bytes_read;
                        $total_bytes_read += $read_count;
                    }
                } else {
                    $total_bytes_read += $read_count;
                }

                for ( ; isset( $buffer[ $i ] ); $i++ ) {
                    if ( "'" == $buffer[ $i ] && ! $quotes[1] ) {
                        // If quote is escaped, ignore
                        if ( ! empty( $buffer[ $i - 1 ] ) && '\\' == $buffer[ $i - 1 ] ) {
                            continue;
                        }

                        $quotes[0] = ! $quotes[0];
                        continue;
                    }

                    if ( '"' == $buffer[ $i ] && ! $quotes[0] ) {
                        // If quote is escaped, ignore
                        if ( ! empty( $buffer[ $i - 1 ] ) && '\\' == $buffer[ $i - 1 ] ) {
                            continue;
                        }

                        $quotes[1] = ! $quotes[1];
                        continue;
                    }

                    $is_quoted = in_array( true, $quotes, true );

                    if ( '{' == $buffer[ $i ] && ! $is_quoted ) {
                        if ( $depth == $start_depth ) {
                            $start = $total_bytes_read - $read_count + $i;
                        }
                        $depth++;
                    }

                    if ( '}' == $buffer[ $i ] && ! $is_quoted ) {
                        $depth--;
                        if ( $depth == $start_depth ) {
                            $end = $total_bytes_read - $read_count + $i +1;
                            break 2;
                        }
                    }
                }
            }
        }

        $chunk = '';

        if ( false !== $start && false !== $end ) {
            fseek( $fp, $start, SEEK_SET );
            $chunk = fread( $fp, $end - $start );
        }

        fseek( $fp, $cur_pos, SEEK_SET );

        return $chunk;
    }
}
