<?php
    /*
     * This class contains a method to do validity checks on ISBNs.
     * it's pretty basic and should require no further docs
     *
     * Updated to be more flexible in the face of
     *
     * copyright 1999, 2004, 2008 Keith Nunn
     * hide@address.com
     * Released under the terms of the GNU General Public License v.2 or later
     */

    class ISBNtest
    {
        private $isbn10 = FALSE; // the stripped ISBN-10, includes given checkdigit
        private $isbn13 = FALSE; // the stripped ISBN-13 (or Bookland EAN), includes given checkdigit
        private $gtin14 = FALSE; // the stripped GTIN-14 (or ISBN-14), includes given checkdigit
        private $error = "";  // error to return, if required

        private function get_isbn10_checkdigit()
        // calculate the checkdigit for ISBN-10
        {
            if (strlen($this->isbn10) != 10)
            {
                return FALSE;
                $this->error = "Given ISBN-10 is not 10 digits (" . $this->isbn10 . ")";
            }
            /* 
             * this checkdigit calculation could probably be expressed in less 
             * space using a lop, but this keeps it very clear what the math
             * involved is 
             */
            $checkdigit = 11 - ( ( 10 * substr($this->isbn10,0,1) + 9 * substr($this->isbn10,1,1) + 8 * substr($this->isbn10,2,1) + 7 * substr($this->isbn10,3,1) + 6 * substr($this->isbn10,4,1) + 5 * substr($this->isbn10,5,1) + 4 * substr($this->isbn10,6,1) + 3 * substr($this->isbn10,7,1) + 2 * substr($this->isbn10,8,1) ) % 11);
            /*
             * convert the numeric check value
             * into the single char version
             */
            switch ( $checkdigit ) 
            {
            case 10:
                $checkdigit = "X";
                break;
            case 11:
                $checkdigit = 0;
                break;
            default:
            }
            return $checkdigit;
        }
        /***********************************************/
        
        private function get_isbn13_checkdigit()
        // calculate the checkdigit for ISBN-10
        {
            if (strlen($this->isbn13) != 13)
            {
                return FALSE;
                $this->error = "Given ISBN-13 is not 10 digits (" . $this->isbn13 . ")";
            }
            /* 
             * this checkdigit calculation could probably be expressed in less 
             * space using a lop, but this keeps it very clear what the math
             * involved is 
             */
            $checkdigit = 10 - ( ( 1 * substr($this->isbn13,0,1) + 3 * substr($this->isbn13,1,1) + 1 * substr($this->isbn13,2,1) + 3 * substr($this->isbn13,3,1) + 1 * substr($this->isbn13,4,1) + 3 * substr($this->isbn13,5,1) + 1 * substr($this->isbn13,6,1) + 3 * substr($this->isbn13,7,1) + 1 * substr($this->isbn13,8,1) + 3 * substr($this->isbn13,9,1) + 1 * substr($this->isbn13,10,1) + 3 * substr($this->isbn13,11,1) ) % 10 );
            /*
             * convert the numeric check value
             * into the single char version
             */
            if ( $checkdigit == 10 ) 
            {
                $checkdigit = "0";
            }
            return $checkdigit;
        }
        /***********************************************/

        private function get_gtin14_checkdigit()
        // calculate the checkdigit for GTIN
        {
            if (strlen($this->gtin14) != 14)
            {
                return FALSE;
                $this->error = "Given GTIN is not 14 digits (" . $this->gtin14 . ")";
            }
            $checkdigit = 10 - ( ( 3 * substr($gtin14,0,1) + 1 * substr($gtin14,1,1) + 3 * substr($gtin14,2,1) + 1 * substr($gtin14,3,1) + 3 * substr($gtin14,4,1) + 1 * substr($gtin14,5,1) + 3 * substr($gtin14,6,1) + 1 * substr($gtin14,7,1) + 3 * substr($gtin14,8,1) + 1 * substr($gtin14,9,1) + 3 * substr($gtin14,10,1) + 1 * substr($gtin14,11,1) + 3 * substr($gtin14,12,1) ) % 10 );
            /*
             * convert the numeric check value
             * into the single char version
             */
            if ( $checkdigit == 10 ) 
            {
                $checkdigit = "0";
            }
            return $checkdigit;
        }
        /***********************************************/

        public function set_isbn10($isbn)
        {
            $isbn = ereg_replace("[^0-9X]","",strtoupper($isbn)); // strip to the basic ISBN
            if (strlen($isbn)==10)
            {
                $this->isbn10 = $isbn;
            }
            else
            {
                $this->error = "ISBN-10 given is not 10 digits ($isbn)";
                return FALSE;
            }
        }
        /***********************************************/

        public function set_isbn13($isbn)
        {
            $isbn = ereg_replace("[^0-9]","",strtoupper($isbn)); // strip to the basic ISBN
            if (strlen($isbn)==13)
            {
                $this->isbn13 = $isbn;
            }
            else
            {
                $this->error = "ISBN-13 given is not 13 digits ($isbn)";
                return FALSE;
            }
        }
        /***********************************************/

        public function set_gtin14($isbn)
        {
            $isbn = ereg_replace("[^0-9]","",strtoupper($isbn)); // strip to the basic ISBN
            if (strlen($isbn)==14)
            {
                $this->gtin14 = $isbn;
            }
            else
            {
                $this->error = "GTIN given is not 14 digits ($isbn)";
                return FALSE;
            }
        }
        /***********************************************/

        public function set_isbn($isbn)
        // trying to provide a common interface here so it's possible to cope 
        // if you don't know for sure what you have -- provided the data is valid
        {
            $isbn = ereg_replace("[^0-9X]","",strtoupper($isbn)); // strip to the basic ISBN
            if (strlen($isbn)==14)
            {
                $this->set_gtin14($isbn);
                return TRUE;
            }
            if (strlen($isbn)==13)
            {
                $this->set_isbn13($isbn);
                return TRUE;
            }
            elseif (strlen($isbn)==10)
            {
                $this->isbn10 = $isbn;
                return TRUE;
            }
            else
            {
                $this->error = "ISBN given is not 10, 13, or 14 digits ($isbn)";
                return FALSE;
            }
        }
        /***********************************************/
        
        public function valid_isbn10($isbn="")
        // report on the validity of the ISBN-10 we have or are given
        {
            if ($isbn != "") // If we've been given a new ISBN then use it.
            {
                $this->set_isbn10($isbn);
            }
            if ( FALSE === $this->isbn10 && FALSE !== $this->isbn13 )
            {
                if ( TRUE === $this->valid_isbn13() )
                {
                    $this->get_isbn10();
                }
            }
            if ( FALSE === $this->isbn10 || strlen($this->isbn10) != 10 )
            {
                $this->error = "ISBN-10 is not set";
                return FALSE;
            }
            if ( (string) substr($this->isbn10,9,1) === (string) $this->get_isbn10_checkdigit() )
            {
                return TRUE;
            }
            else
            {
                $this->error = "Checkdigit failure";
                return FALSE;
            }
        }
        /***********************************************/
        
        public function valid_isbn13($isbn="")
        // report on the validity of the ISBN-13 we have or are given
        {
            if ($isbn != "") // if we've been given an isbn here, use it
            { 
                $this->set_isbn13($isbn);
            }
            if ( FALSE === $this->isbn13 && FALSE !== $this->isbn10 )
            {
                if ( TRUE === $this->valid_isbn10() )
                {
                    $this->get_isbn13();
                }
            }
            if ( FALSE === $this->isbn13 || strlen($this->isbn13) != 13 )
            {
                $this->error = "ISBN-13 is not set";
                return FALSE;
            }
            if ( (string) substr($this->isbn13,12,1) === (string) $this->get_isbn13_checkdigit() )
            {
                return TRUE;
            }
            else
            {
                $this->error = "Checkdigit failure";
                return FALSE;
            }
        }
        /***********************************************/
        
        public function valid_gtin14($isbn="")
        // report on the validity of the GTIN we have or are given
        {
            if ($isbn != "") // if we've been given an ISBN here, use it
            {
                $this->set_gtin14($isbn);
            }
            if ( substr($this->gtin14,13,1) === $this->get_gtin14_checkdigit() )
            {
                return TRUE;
            }
            else
            {
                $this->error = "Checkdigit failure";
                return FALSE;
            }
        }
        /***********************************************/
        public function valid_isbn($isbn="")
        // trying to provide a common interface here so it's possible to cope 
        // if you don't know for sure what you have -- provided the data is valid
        {
            if ($isbn != "") // if we've been given an ISBN then use it
            {
                $this->set_isbn($isbn);
            }
            if ( (isset($this->gtin14) && $this->valid_gtin14() == TRUE) || (isset($this->isbn13) && $this->valid_isbn13() == TRUE) || (isset($this->isbn10) && $this->valid_isbn10() == TRUE) ) // in this routine, we don't care what kind it is, only that it's valid.
            {
                return TRUE;
            }
            else
            {
                $this->error = "Checkdigit failure";
                return FALSE;
            }
        }
        /***********************************************/
        
        public function get_isbn10()
        // return the ISBN-10 that has been set or create one if we have a valid ISBN-13
        {
            if ( $this->isbn10 != FALSE )
            {
                return $this->isbn10;
            }
            elseif ( $this->valid_isbn13() != FALSE )
            {
                if ( eregi("979", $this->isbn13) )
                {
                    $this->error = "979 Bookland EAN values can't be converted to ISBN-10";
                    return FALSE; // if it's a 979 prefix it can't be downgraded
                }
                else
                {
                    $this->set_isbn10(substr($this->isbn13, 3, 10)); // invalid ISBN used as a temp value for next step
                    $checkdigit = $this->get_isbn10_checkdigit();
                    $this->set_isbn10(substr($this->isbn13, 3, 9) . $checkdigit); // true value (I hope)
                    return $this->isbn10;
                }
            }
            else
            {
                $this->error = "No ISBN-10 value set or calculable";
                return FALSE;
            }
        }
        /*********************************************/
        
        public function get_isbn13()
        // return the ISBN-13 that has been set or create one if we have a valid ISBN-10
        {
            if ( $this->isbn13 != FALSE )
            {
                return $this->isbn13;
            }
            elseif ( $this->valid_isbn10() != FALSE )
            {
                $this->set_isbn13("978" . substr($this->isbn10, 0, 9) . "0"); // invalid ISBN used as a temp value for next step
                $checkdigit = (string) $this->get_isbn13_checkdigit();
                $this->set_isbn13("978" . substr($this->isbn10, 0, 9) . $checkdigit); // true value (I hope)
                return $this->isbn13;
            }
            else
            {
                $this->error = "No ISBN-10 value set or calculable";
                return FALSE;
            }
        }
        /*********************************************/ 
        
        public function get_error()
        // return the error message
        {
            return $this->error;
        }
        /*********************************************/ 
        
    }
?>