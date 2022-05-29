<?php
// This file is part of the Contact Form plugin for Moodle - http://moodle.org/
//
// Contact Form is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Contact Form is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Contact Form.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin for Moodle is used to send emails through a web form.
 *
 * @package    local_contact
 * @copyright  2016-2022 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * local_contact class. Handles processing of information submitted from a web form.
 * @copyright  2016-2022 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_contact {

    /**
     * Class constructor. Receives and validates information received through a
     * web form submission.
     *
     * @return     True  if the information received passes our spambot detection. False if it fails.
     */
    public function __construct() {
        global $CFG;
		
		/*
			Моя версия формы используется при добавлении в форму скрытого поля customform с любым непустым значением.
			Если такое поле отсутвует или пустое, используется оригинальный код плагина.
		*/
		
		$this->fromcustomform = trim(optional_param('customform','',PARAM_TEXT)); // Получаем значение из $_POST['customform']. Если поле отсуствует, помещаем в переменную пустую строку.
		
		$this->frommarkdown = trim(optional_param('markdown','',PARAM_TEXT)); // Получаем значение дополнительной почты, на которую будет отправлено письмо с разметкой Markdown. Если поле отсутсвует помещаем в переменную пустую строку
		
		if ($this->fromcustomform !== '') // В форме есть непустое поле customform, используем мою версию формы. Для проверки используется не empty, на случай, например, строки '0'
		{
			$this->fromfirstname = trim(optional_param('firstname','',PARAM_TEXT)); // Получаем имя из формы, если поля в форме нет, помещаем пустое значение
			$this->fromlastname = trim(optional_param('lastname','',PARAM_TEXT)); // Получаем фамилию из формы, если поля в форме нет, помещаем пустое значение
			$this->frompatronim = trim(optional_param('patronim','',PARAM_TEXT)); // Получаем отчество из формы, если поля в форме нет, помещаем пустое значение
			$this->fromusername = trim(optional_param('username','',PARAM_TEXT)); // Получаем логин из формы, если поля в форме нет, помещаем пустое значение
			$this->fromemail = trim(optional_param('email','',PARAM_TEXT)); // Получаем почту из формы, если поля в форме нет, помещаем пустое значение
			
			$this->fromemail_letter = ''; // Помещаем пустую строку, из которой будем добавлять почту в тело письма. В теле письма поле почта может содержать дополнительную информацию помимо адреса почты, поэтому нельзя использовать одну переменную для проверки на спам или добавления адреса для ответа и для добавления потчы в тело письма
						
			$this->fromsubject = ''; // Для формирования ФИО в теме письма. Нужна отдельная переменная, т.к. к данным ФИО, введенным пользователем, могут добавляться дополнительные данные с сайта, и тема писма получится слишком длинной
			
				
			if (isloggedin() && !isguestuser()) // Пользователь залогинен и не Гость
			{
				global $USER;
				
				//Фамилия
				if(($this->fromlastname !== '') && !($this->compmestr($this->fromlastname, $USER->lastname))) // Фамилия в форме введена И не совпадает с фамилией на сайте
				{
					$this->fromsubject .= $this->fromlastname; // Добавляем в тему письма фамилию из формы
					$this->fromlastname .= ' (' . $USER->lastname . ')'; // Добавляем фамилию на сайте к фамилии из формы
				}
				else // Фамилия в форме совпадает с фамилией на сайте ИЛИ поле не заполнено (отсуствует) 
				{
					$this->fromsubject .= $USER->lastname; // Добавляем в тему письма фамилию с сайта
					
					unset($this->fromlastname); // Очищаем переменную перед записью на случай, если она не пустая
					$this->fromlastname = $USER->lastname;  //Берем фамилию с сайта
				}
				
				//Имя
				if ($this->fromfirstname === '') // Поле с именем отсуствует ИЛИ не заполнено
				{
					$this->fromsubject .= " " . $USER->firstname; // Добавляем в тему письма имя с сайта
					$this->fromfirstname .= $USER->firstname; //Берем имя с сайта
				}
				else // Поле с именем заполнено
				{
					$this->fromsubject .= " " . $this->fromfirstname; // Добавляем имя из формы в тему письма
					
					if(mb_stripos($USER->firstname, $this->fromfirstname) !== 0) // Имя в форме не сопадает с началом имени на сайте
					{
						$this->fromfirstname .= ' (' . $USER->firstname . ')'; //Добавляем имя на сайте к имени из формы
					}	
					// Если имя в форме совпадает с началом имени на сайте, берем имя из формы, т.е. ничего не меняем в $this->fromfirstname
				}
							
				//Почта
				if(($this->fromemail !== '') && !$this->compmestr($this->fromemail, $USER->email)) // Почта в форме заполнена И не совпадает с почтой на сайте
				{
					$this->fromemail_letter .= $this->fromemail . ' (' . $USER->email . ')'; // Добавляем почту с сайта к почте из формы для отображения в письме
				}
				else //Почта в форме совпадает с почтой на сайте или поле отсуствует (не заполнено)
				{
					$this->fromemail_letter .= $USER->email;  //Берем почту с сайта для отображения в письме
					
					if ($this->fromemail === '') //Если пользователь не заполнил поле с почтой
					{
						$this->fromemail_letter .= ' / ' . get_string('emptyemailfield','local_contact'); // Добавляем сообщение о том, что пользователь не указал адрес почты.  Потому что если пользователь указал кантиановскую почту в форме не сам, не факт, что он ей пользуется
						$this->fromemail .= $USER->email; // Берем почту для проверки на спам и в поле «ответить» с сайта
					}					
				}
								
				//Логин
				unset($this->fromusername); //На всякий случай очищаем переменную перед присвоением нового значения
				$this->fromusername = $USER->username . ' / ' . get_string('eventuserloggedin', 'auth'); // Логин берем с сайта и добавляем к нему отметку о том, что пользователь залогинен
			}
		
			else //Пользователь не залогинен или Гость
			{
				// Проверяем, присутсвуют ли в форме поля firstname, lastname и email
				
				if ($this->fromfirstname === '') // Если поле для имени не заполнено ИЛИ его нет
				{
					required_param('firstname', PARAM_TEXT); // Выдаем ошибку, если в форме поля для имени
				}
				
				if ($this->fromlastname === '') // Если поле для фамилии не заполнено ИЛИ его нет
				{
					required_param('lastname', PARAM_TEXT); // Выдаем ошибку, если в форме нет поля для фамилии
				}
				
				if ($this->fromemail === '') // Если поле для почты не заполнено ИЛИ его нет
				{
					required_param('email', PARAM_TEXT); // Выдаем ошибку, если в форме нет поля для почты
				}
				
				/*Если required_param не выдали ошибки*/
				
								
				$user_found_by_email = false; // Если не сможем найти на сайте пользователя по почте, используем эту переменную, чтобы запустить поиск по логину
				
				if($this->fromemail !== '') // Проверяем, заполнено ли поле с почтой. Потому что если оно совсем пустое, и в базе данных есть пользователи без почты, функция findmeuserbyemail выдаст Notice
				{
					$user_info_mail = $this->findmeuserbyemail($this->fromemail); // Пытаемся найти незалогиненного пользователя по адресу почты
				
					if($user_info_mail['firstname'] !== 'usernotfound' && $user_info_mail['firstname'] !== 'multipleusers' ) // Если по почте найден единтсвенный пользователь
					{
						$user_found_by_email = true; // Не нужно искать пользователя по логину
						
						//Фамилия
						if(($this->fromlastname !== '') && !$this->compmestr($this->fromlastname, $user_info_mail['lastname'])) // Фамилия в форме заполнена И не совпадает с фамилией на сайте
						{
							$this->fromsubject .= $this->fromlastname; //В тему письма берем фамилию из формы
							$this->fromlastname .= ' (' . $user_info_mail['lastname'] . ')'; // Добавляем к фамилии из формы фамилию на сайте
						}
						else //Фамилия в форме совпадает с фамилией на сайте ИЛИ поле не заполнено
						{
							$this->fromsubject .= $user_info_mail['lastname']; // В тему письма берем фамилию с сайта
							
							unset($this->fromlastname);
							$this->fromlastname = $user_info_mail['lastname']; // Берем фамилию с сайта
						}
				
						//Имя
						if ($this->fromfirstname === '') // Если поле с именем не заполнено
						{
							$this->fromsubject .= " " . $user_info_mail['firstname']; // В тему письма берем имя с сайта
							$this->fromfirstname .= $user_info_mail['firstname']; // Берем имя с сайта
						}
						else //Поле с именем заполнено
						{
							$this->fromsubject .= " " . $this->fromfirstname; // В тему письма берем имя из формы
							
							if(mb_stripos($user_info_mail['firstname'], trim($_POST['firstname'])) !== 0) // Если имя в форме не сопадает с началом имени на сайте
							{
								$this->fromfirstname .= ' (' . $user_info_mail['firstname'] . ')'; // Добавляем к имени из формы имя с сайта
							}	
							// Если имя в форме совпадает с началом имени на сайте, ничего не меняем в имени из формы							
						}
				
						//Почта
						$this->fromemail_letter .= $this->fromemail; //Почту, введенную пользователем (здесь обязательно совпадает с почтой на сайте), берем для добавления в письмо
										
						//Логин
						if(($this->fromusername !== '') && compmestr($this->fromusername,$user_info_mail['username']) !== 0) // Если логин введен И не совпадает с логином на сайте
						{
							$this->fromusername .= ' (' . $user_info_mail['username'] . ')' . ' / ' . $user_info_mail['userstatus']; // Добавляем логин на сайте к логину, введенному пользователем и добавлем сообщение о том, что пользователь разлогинен
						}
						else //Логин не введен ИЛИ совпадает с логином на сайте
						{
							$this->fromusername .= $user_info_mail['username'] . ' / ' . $user_info_mail['userstatus']; // Берем логин с сайта и добавляем сообщение о том, что пользователь разлогинен
						}
					}
					else //Если почта введена, но не нашли пользователя по почте
					{
						if($user_info_mail['firstname'] === 'usernotfound') // Почты нет на сайте
						{
							$why_not_found_by_email = 1;
						}
						if($user_info_mail['firstname'] === 'multipleusers') // Несколько пользователей с одинаковой почтой
						{
							$why_not_found_by_email = 2;
						}
					}
				}
				else // Поле с почтой не заполнено
				{
					$why_not_found_by_email = 0;	
				}
				
				//Если пользователь не найден по почте, и пользователь указал логин, пытаемся найти по логину
				if(($this->fromusername !== '') && !$user_found_by_email) //Если логин введен И по почте пользователя не нашли
				{
					$user_info_login = $this->findmeuserbylogin($this->fromusername);	// Пытаемся найти пользователя по логину
					
					if($user_info_login['firstname'] !== 'usernotfound') // Если нашли по логину
					{
						//Фамилия
						if(($this->fromlastname !== '') && !$this->compmestr($this->fromlastname, $user_info_login['lastname'])) // Фамилия в форме заполнена И не совпадает с фамилией на сайте
						{
							$this->fromsubject .= $this->fromlastname; // В тему письма добавляем фамилию из формы
							$this->fromlastname .= ' (' . $user_info_login['lastname'] . ')'; // Добавляем фамилию с сайта к фамилии из формы							
						}
						else //Фамилия в форме совпадает с фамилией на сайте ИЛИ поле не заполнено
						{
							$this->fromsubject .= $user_info_login['lastname']; // В тему письма добавляем фамилию с сайта
							
							unset($this->fromlastname); // Очищаем переменную на случай, если поле заполнено
							$this->fromlastname = $user_info_login['lastname']; // Берем фамилию с сайта
						}
					
						//Имя
						if ($this->fromfirstname === '') // Поле с именем не заполнено
						{
							$this->fromsubject .= " " . $user_info_login['firstname']; // Добавляем в тему письма имя с сайта
							
							unset($this->fromfirstname); // Очищаем переменную на случай, если поле заполнено
							$this->fromfirstname = $user_info_login['firstname']; // Берем имя с сайта
						}
						else // Поле с именем заполнено 
						{
							$this->fromsubject .= " " . $this->fromfirstname; // В тему письма берем имя из формы
							
							if(mb_stripos($user_info_login['firstname'], $this->fromfirstname) !== 0) // Имя в форме не сопадает с началом имени на сайте
							{
								$this->fromfirstname .= ' (' . $user_info_login['firstname'] . ')'; // Добавляем имя с сайта к имени из формы
							}	
							// Если имя в форме совпадает с началом имени на сайте, ничего не меняем в имени из формы					
						}
					
						//Почта
						switch($why_not_found_by_email) // К почте добавляем информацию о том, почему не нашли пользователя по почте
						{
							case 0: // Поле с почтой не заполнено
									$this->fromemail_letter .= $user_info_login['email'] . " / " . get_string('emptyemailfield','local_contact'); // Берем почту с сайта и добавляем сообщение о том, что пользовтель не заполнил поле с почтой
									$this->fromemail .= $user_info_login['email']; // Почта с сайта для проверки на спам и поля «ответить», чтобы пройти проверку на спам, если почты нет, но указан корректный логин
									break;
							case 1: // Поле с почтой заполнено, но почты нет на сайте
									if(!empty($user_info_login['email'])) // Поле с почтой в базе данных не пустое
									{
										$this->fromemail_letter =  $this->fromemail . ' (' . $user_info_login['email'] . ')'; // Добавляем к почте из формы почту на сайте
									}
									else //Поле с почтой в базе данных пустое
									{
										$this->fromemail_letter .= $this->fromemail . ' / ' . get_string('emptyemailindatabase','local_contact'); // Добавляем к почте из формы сообщение о том, что в базе данных поле с почтой пустое
									}
									break;
							case 2: // Поле с почтой заполнено, но одинаковая почта у нескольких пользователей
									$this->fromemail_letter .= $this->fromemail . " / " . $user_info_mail['userstatus']; // Берем почту из формы, добавляем сообщение о том, указанная пользователем почта у нескольких пользователей
									break;
						}
														
						//Логин
						$this->fromusername .= ' / ' . $user_info_login['userstatus']; // По логину нашли. Берем логин из формы и добавляем сообщение о том, что пользователь разлогинен
					}
						
					else // Если не нашли и по логину тоже, хотя логин введен
					{
								
						$this->fromusername .= ' / ' . $user_info_login['userstatus']; //Берем логин из формы и добавляем сообщение о том, что на сайте логин не найден
						if($why_not_found_by_email === 0) // Если не нашли пользователя по почте, потому что поле пустое
						{
						// Ничего не делаем, все равно проверка на спам выдаст в итоге ошибку, и письмо не будет отправлено
						}
						else //Если не нашли пользователя по почте, потому что почты нет на сайте или она у нескольких пользователей
						{
						$this->fromemail_letter .= $this->fromemail . ' / ' . $user_info_mail['userstatus']; // К почте добавляем собщение, о том, что пользователь с такой почтой не найден или найдено более одного пользователя
						}
						
						$this->fromsubject .= $this->fromlastname . " " . $this->fromfirstname; // В тему письма берем фамилию и имя из формы
					}
				}
				
				else // Если логин не введен или поле для логина отсутсвует 
				{
						// В fromusername выше помещается пустое значение посредством optional_param
						if($why_not_found_by_email === 0) // Если не нашли пользователя по почте, потому что поле пустое
						{
						// Ничего не делаем, все равно проверка на спам выдаст в итоге ошибку, и письмо не будет отправлено
						}
						else //Если не нашли пользователя по почте, потому что почты нет на сайте или она у нескольких пользователей
						{
						$this->fromemail_letter .= $this->fromemail . ' / ' . $user_info_mail['userstatus']; // К почте добавляем собщение, о том, что пользователь с такой почтой не найден или найдено более одного пользователя
						}
					$this->fromsubject .= $this->fromlastname . " " . $this->fromfirstname; // В тему письма берем фамилию и имя из формы
				}
					
			}// Конец блока обработки случая, когда пользователь не залогинен или Гость
			
		
				
			/*Помещаем нужные данные в $_POST в нужном порядке и удаляем из $_POST ненужные поля, чтобы они не отображались в письме*/
		
						
			if(($this->fromlastname !== '') || ($this->fromfirstname !== '') || ($this->frompatronim !== '')) //Если заполнено хотя бы одно поле из ФИО
			{
				$this->fromname = trim(preg_replace('/\s+/', ' ', $this->fromlastname . ' ' . $this->fromfirstname . ' ' . $this->frompatronim)); //Составляем полное имя из фамилии, имени и отчества (если есть). Удаляем лишние пробелы, если
			}
			else //Если ничего из ФИО не указано, помещаем в fromname пустое значение, чтобы пользователь не прошел проверку на спам
			{
				$this->fromname = '';
			}
		
			$this->fromsubject .= " " . $this->frompatronim; //Добавляем отчество к теме письма
			
		
			$this->fromname_form = $this->fromsubject; // Создаем свое имя отправителя из темы письма без возможных дополнений данными с сайта до того, как добавим к теме письма время отправления, чтобы в почте все выглядело красиво, а не только в Trello
			
			$this->fromtime = optional_param('time','',PARAM_TEXT); //Получаем данные из поля время, если поля нет или оно пустое, помещаем в переменную пустую строку
			
			if($this->fromtime !== '') //Если поле с временем существует и не пустое
			{
			$this->fromsubject .= " " . $this->fromtime; //Добавляем время в тему письма
			}
			
			$this->fromsubject = trim(preg_replace('/\s+/', ' ', $this->fromsubject)); //На случай, если из-за отсуствия каких-то данных затесались лишние пробелы
		
			if (array_key_exists('patronim', $_POST))
			{
			unset($_POST['patronim']); // Убираем поле с отчеством, потому что отчество включено в полное имя
			}
			
			if (array_key_exists('firstname', $_POST))
			{
			unset($_POST['firstname']); //Убираем поле с именем, потому что имя уже включено в fromname
			}
			
			if (array_key_exists('lastname', $_POST))
			{
			unset($_POST['lastname']); //Убираем поле с фамилией, потому что фамилия уже в fromname
			}
			
			if (array_key_exists('email', $_POST))
			{
			unset($_POST['email']); // Убираем поле с почтой, потому что почта уже хранится в fromemail и fromemail_letter, и будет добавлена на нужную позицию
			}
			
			//Поле с полным именем теперь не является обязательным, добавить в дальнейшем в плагин правила для обработки случая, когда используется это поле, вместо отдельных полей с именем и фамилией? Пока очищаем это поле на всякий случай, потому что в него будут помещены ФИО из fromname
			
			if(!empty($_POST['name']))
			{
				unset($_POST['name']);
			}
			
			$_POST = array_merge(array('email' => $this->fromemail_letter), $_POST); // Добавляем почту в $_POST на третье место
			
			if (array_key_exists('username',$_POST)) //Поле username для логина не является обязательным, поэтому добавляем логин только если поле присуствует в форме
			{
			unset($_POST['username']); // Удаляем поле с логином, чтобы поместить логин на второе место
			$_POST = array_merge(array('username' => $this->fromusername), $_POST); // Добавляем логин в $_POST на второе место
			}
			
			$_POST = array_merge(array('name' => $this->fromname), $_POST);	// Добавляем ФИО в $_POST на первое место
			

			
		}//Конец моей весии формы
		
		
		if($this->fromcustomform === '')//Используем оригинальную версию формы
		{   
			if (isloggedin() && !isguestuser()) 
			{
				// If logged-in as non guest, use their registered fullname and email address.
				global $USER;
				$this->fromname = get_string('fullnamedisplay', null, $USER);
				$this->fromemail = $USER->email;
				 // Insert name and email address at first position in $_POST array.
				if (!empty($_POST['email']))
				{
					unset($_POST['email']);
				}
				if (!empty($_POST['name']))
				{
					unset($_POST['name']);
				}
				$_POST = array_merge(array('email' => $this->fromemail), $_POST);
				$_POST = array_merge(array('name' => $this->fromname), $_POST);
			}
			else
			{
				// If not logged-in as a user or logged in a guest, the name and email fields are required.
				if (empty($this->fromname  = trim(optional_param(get_string('field-name', 'local_contact'), '', PARAM_TEXT))))
				{
					$this->fromname = required_param('name', PARAM_TEXT);
				}
				if (empty($this->fromemail = trim(optional_param(get_string('field-email', 'local_contact'), '', PARAM_EMAIL))))
				{
					$this->fromemail = required_param('email', PARAM_TEXT);
				}
			}
			
			$this->fromname = trim($this->fromname);
			$this->fromemail = trim($this->fromemail);
		}//Конец оригинальной версии формы
		

        $this->isspambot = false;
        $this->errmsg = '';

        if ($CFG->branch >= 32) {
            // As of Moodle 3.2, $CFG->emailonlyfromnoreplyaddress has been deprecated.
            $CFG->emailonlyfromnoreplyaddress = !empty($CFG->noreplyaddress);
        }

        // Did someone forget to configure Moodle properly?

        // Validate Moodle's no-reply email address.
        if (!empty($CFG->emailonlyfromnoreplyaddress)) {
            if (!$this->isspambot && !empty($CFG->emailonlyfromnoreplyaddress)
                    && $this->isspambot = !validate_email($CFG->noreplyaddress)) {
                $this->errmsg = 'Moodle no-reply email address is invalid.';
                if ($CFG->branch >= 32) {
                    $this->errmsg .= '  (<a href="../../admin/settings.php?section=outgoingmailconfig">change</a>)';
                } else {
                    $this->errmsg .= '  (<a href="../../admin/settings.php?section=messagesettingemail">change</a>)';
                }
            }
        }

        // Use primary administrators name and email address if support name and email are not defined.
        $primaryadmin = get_admin();
        $CFG->supportemail = empty($CFG->supportemail) ? $primaryadmin->email : $CFG->supportemail;
        $CFG->supportname = empty($CFG->supportname) ? fullname($primaryadmin, true) : $CFG->supportname;

        // Validate Moodle's support email address.
        if (!$this->isspambot && $this->isspambot = !validate_email($CFG->supportemail)) {
            $this->errmsg = 'Moodle support email address is invalid.';
            $this->errmsg .= ' (<a href="../../admin/settings.php?section=supportcontact">change</a>)';
        }

        // START: Spambot detection.

        // File attachments not supported.
        if (!$this->isspambot && $this->isspambot = !empty($_FILES)) {
            $this->errmsg = 'File attachments not supported.';
        }

        // Validate submit button.
        if (!$this->isspambot && $this->isspambot = !isset($_POST['submit'])) {
            $this->errmsg = 'Missing submit button.';
        }

        // Limit maximum number of form $_POST fields to 1024.
        if (!$this->isspambot) {
            $postsize = @count($_POST);
            if ($this->isspambot = ($postsize > 1024)) {
                $this->errmsg = 'Form cannot contain more than 1024 fields.';
            } else if ($this->isspambot = ($postsize == 0)) {
                $this->errmsg = 'Form must be submitted using POST method.';
            }
        }

        // Limit maximum size of allowed form $_POST submission to 256 KB.
        if (!$this->isspambot) {
            $postsize = (int) @$_SERVER['CONTENT_LENGTH'];
            if ($this->isspambot = ($postsize > 262144)) {
                $this->errmsg = 'Form cannot contain more than 256 KB of data.';
            }
        }

        // Validate if "sesskey" field contains the correct value.
        if (!$this->isspambot && $this->isspambot = (optional_param('sesskey', '3.1415', PARAM_RAW) != sesskey())) {
            $this->errmsg = '"sesskey" field is missing or contains an incorrect value.';
        }

        // Validate referrer URL.
        if (!$this->isspambot && $this->isspambot = !isset($_SERVER['HTTP_REFERER'])) {
            $this->errmsg = 'Missing referrer.';
        }
        if (!$this->isspambot && $this->isspambot = (stripos($_SERVER['HTTP_REFERER'], $CFG->wwwroot) != 0)) {
            $this->errmsg = 'Unknown referrer - must come from this site: ' . $CFG->wwwroot;
        }

        // Validate sender's email address.
        if (!$this->isspambot && $this->isspambot = !validate_email($this->fromemail)) {
            $this->errmsg = 'Unknown sender - invalid email address or the form field name is incorrect.';
        }

        // Validate sender's name.
        if (!$this->isspambot && $this->isspambot = empty($this->fromname)) {
            $this->errmsg =  'Missing sender - invalid name or the form field name is incorrect';
        }

        // Validate against email address whitelist and blacklist.
        $skipdomaintest = false;
        // TODO: Create a plugin setting for this list.
        $whitelist = ''; // Future code: $config->whitelistemails .
        $whitelist = ',' . $whitelist . ',';
        // TODO: Create a plugin blacklistemails setting.
        $blacklist = ''; // Future code: $config->blacklistemails .
        $blacklist = ',' . $blacklist . ',';
        if (!$this->isspambot && stripos($whitelist, ',' . $this->fromemail . ',') != false) {
            $skipdomaintest = true; // Skip the upcoming domain test.
        } else {
            if (!$this->isspambot && $blacklist != ',,'
                    && $this->isspambot = ($blacklist == '*' || stripos($blacklist, ',' . $this->fromemail . ',') == false)) {
                // Nice try. We know who you are.
                $this->errmsg = 'Bad sender - Email address is blacklisted.';
            }
        }

        // Validate against domain whitelist and blacklist... except for the nice people.
        if (!$skipdomaintest && !$this->isspambot) {
            // TODO: Create a plugin whitelistdomains setting.
            $whitelist = ''; // Future code: $config->whitelistdomains .
            $whitelist = ',' . $whitelist . ',';
            $domain = substr(strrchr($this->fromemail, '@'), 1);

            if (stripos($whitelist, ',' . $domain . ',') != false) {
                // Ya, you check out. This email domain is gold here!
                $blacklist = '';
            } else {
                 // TODO: Create a plugin blacklistdomains setting.
                $blacklist = 'example.com,example.net,sample.com,test.com,specified.com'; // Future code:$config->blacklistdomains .
                $blacklist = ',' . $blacklist . ',';
                if ($blacklist != ',,'
                        && $this->isspambot = ($blacklist == '*' || stripos($blacklist, ',' . $domain . ',') != false)) {
                    // Naughty naughty. We know all about your kind.
                    $this->errmsg = 'Bad sender - Email domain is blacklisted.';
                }
            }
        }

        // TODO: Test IP address against blacklist.

        // END: Spambot detection... Wait, got some photo ID on you? ;-) .
    }

    /**
     * Creates a user info object based on provided parameters.
     *
     * @param      string  $email  email address.
     * @param      string  $name   (optional) Plain text real name.
     * @param      int     $id     (optional) Moodle user ID.
     *
     * @return     object  Moodle userinfo.
     */
    private function makeemailuser($email, $name = '', $id = -99) {
        $emailuser = new stdClass();
        $emailuser->email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailuser->email = '';
        }
        $emailuser->firstname = format_text($name, FORMAT_PLAIN, array('trusted' => false));
        $emailuser->lastname = '';
        $emailuser->maildisplay = true;
        $emailuser->mailformat = 1; // 0 (zero) text-only emails, 1 (one) for HTML emails.
        $emailuser->id = $id;
        $emailuser->firstnamephonetic = '';
        $emailuser->lastnamephonetic = '';
        $emailuser->middlename = '';
        $emailuser->alternatename = '';
        $emailuser->username = '';
        return $emailuser;
    }

    /**
     * Send email message and optionally autorespond.
     *
     * @param      string  $email Recipient's Email address.
     * @param      string  $name  Recipient's real name in plain text.
     * @param      boolean  $sendconfirmationemail  Set to true to also send an autorespond confirmation email back to user (TODO).
     *
     * @return     boolean  $status - True if message was successfully sent, false if not.
     */
    public function sendmessage($email, $name, $sendconfirmationemail = false) {
        global $USER, $CFG, $SITE;

       if ($this->fromcustomform !== '') //Если используем мою версию формы, в качестве имени отправителя берем имя без возможных добавок с сайта
	   {
			$from = $this->makeemailuser($this->fromemail, $this->fromname_form);
	   }
		
		if ($this->fromcustomform === '') //Используем оригинальный код
		{
	   // Create the sender from the submitted name and email address.
        $from = $this->makeemailuser($this->fromemail, $this->fromname);
		}

        // Create the recipient.
        $to = $this->makeemailuser($email, $name);
		
		if (($this->fromcustomform !== '') && ($this->frommarkdown) !== '') //Если используем мою форму, проверяем есть ли непустое дополнительное поле с адресом для отправки сообщения в формате Markdown в Trello
		{
			$lines = explode("\n", get_config('local_contact', 'recipient_list'));
			foreach ($lines as $linenumbe => $line)
			{
				$line = trim($line);
				if (empty($line)) // Blank line.
				{
					continue;
				}
				$thisrecipient = explode('|', $line);
				// 0 = alias, 1 = email address, 2 = name.
				if (count($thisrecipient) == 3) {
					// Trim leading and trailing spaces from each of the 3 parameters.
					$thisrecipient = array_map('trim', $thisrecipient);
					// See if this alias matches the one we are looking for.
					if ($thisrecipient[0] == $this->frommarkdown && !empty($thisrecipient[1]) && !empty($thisrecipient[2]))
					{
						$email_markdown = $thisrecipient[1];
						$name_markdown = $thisrecipient[2];
						break;
					}
				}
			}
			
			$to_markdown = $this->makeemailuser($email_markdown,$name_markdown); // Получатель письма с разметкой Markdown
		}

        // Create the Subject for message.
        $subject = '';
        
		if (($this->fromcustomform !== '') && ($this->fromsubject !== '')) // Если используем мою форму и fromsubject не пустая, используем способ формирования темы из моей формы
		{
			$subject .= $this->fromsubject; 
		}
		else //Используем оригинальный код
		{
		if (empty(get_config('local_contact', 'nosubjectsitename'))) { // Not checked.
            // Include site name in subject field.
            $systemcontext = context_system::instance();
            $subject .= '[' . format_text($SITE->shortname, FORMAT_HTML, ['context' => $systemcontext]) . '] ';
        }
        $subject .= optional_param(get_string('field-subject', 'local_contact'),
                get_string('defaultsubject', 'local_contact'), PARAM_TEXT);
		}
        // Build the body of the email using user-entered information.

        // Note: Name of message field is defined in the language pack.
        $fieldmessage = get_string('field-message', 'local_contact');

        $htmlmessage = ''; //Здесь собираем текст письма, которое отправляется на почту
		
		$markdown_message = ''; //Здесь собираем текст письма, которое отправляется в Trello

        foreach ($_POST as $key => $value) {

            // Only process key conforming to valid form field ID/Name token specifications.
            if (preg_match('/^[A-Za-z][A-Za-z0-9_:\.-]*/', $key)) {
				
				if ($this->fromcustomform !== '') //Используем мою форму
				{
					// Изменено, чтобы не исключать пустые поля, только поля, которые не нужны в письме
					if (!in_array($key, array('sesskey', 'submit','customform','markdown')) )
					{
                    // Apply minor formatting of the key by replacing underscores with spaces.
                    $key = str_replace('_', ' ', $key);
						switch ($key)
						{						
							// Make custom alterations.
							case 'message': // Message field - use translated value from language file.
                            $key = $fieldmessage;
							case $fieldmessage: // Message field.
                            // Strip out excessive empty lines.
                            $value = preg_replace('/\n(\s*\n){2,}/', "\n\n", $value);
                            // Sanitize the text.
                            $value = format_text($value, FORMAT_PLAIN, array('trusted' => false));
                            // Add to email message.
							$htmlmessage .= '<li><p><strong>' . ucfirst($key) . ':</strong><br>' . $value . '</p></li>';
                            $markdown_message .= '+ **' . ucfirst($key) . ':**<br>' . $value . '<br>';
                            break;
							// Don't include the following fields in the body of the message.
							case 'recipient':                  // Recipient field.
							case 'recaptcha challenge field':  // ReCAPTCHA related field.
							case 'recaptcha response field':   // ReCAPTCHA related field.
							case 'g-recaptcha-response':       // ReCAPTCHA related field.
								break;
							// Use language translations for the labels of the following fields.
							case 'name':        // Name field.
							case 'email':       // Email field.
							case 'subject':     // Subject field.
							case 'username':     // Username field.
							case 'phonenumber':     // Phone number field.
							case 'time':     // Time of submission field.
							case 'referrer':
							case 'role':
								$key = get_string('field-' . $key, 'local_contact');
							
							default:            // All other fields.
								// Join array of values. Example: <select multiple>.
								if (is_array($value))
								{
									//На случай, если множественный выбор реальзован через checkbox и среди чекбоксов присутствует скрытое пустое поле, чтобы если ничего не выбрано, название поля все равно передавалось в _POST и присутствовало в письме
									if (count($value)>1)
									{
										$value = array_diff($value, array(''));
									}
									$value = join(', ', $value);
								}
								// Sanitize the text.
								$value = format_text($value, FORMAT_PLAIN, array('trusted' => false));
								// Add to email message.
								$htmlmessage .= '<li><p><strong>'.ucfirst($key) . ':</strong><br>' . $value . '</p></li>';
								$markdown_message .= '+ **' . ucfirst($key) . ':**<br>' . $value . '<br>' ;
						}
					}
				}
				
				if ($this->fromcustomform === '') //Используем оригинальный код
				{
                // Exclude fields we don't want in the message and empty fields.
                if (!in_array($key, array('sesskey', 'submit')) && trim($value) != '') {

                    // Apply minor formatting of the key by replacing underscores with spaces.
                    $key = str_replace('_', ' ', $key);
						switch ($key) {
                        // Make custom alterations.
                        case 'message': // Message field - use translated value from language file.
                            $key = $fieldmessage;
                        case $fieldmessage: // Message field.
                            // Strip out excessive empty lines.
                            $value = preg_replace('/\n(\s*\n){2,}/', "\n\n", $value);
                            // Sanitize the text.
                            $value = format_text($value, FORMAT_PLAIN, array('trusted' => false));
                            // Add to email message.
                            $htmlmessage .= '<p><strong>' . ucfirst($key) . ' :</strong></p><p>' . $value . '</p>';
                            break;
                        // Don't include the following fields in the body of the message.
                        case 'recipient':                  // Recipient field.
                        case 'recaptcha challenge field':  // ReCAPTCHA related field.
                        case 'recaptcha response field':   // ReCAPTCHA related field.
                        case 'g-recaptcha-response':       // ReCAPTCHA related field.
                            break;
                        // Use language translations for the labels of the following fields.
                        case 'name':        // Name field.
                        case 'email':       // Email field.
                        case 'subject':     // Subject field.
                            $key = get_string('field-' . $key, 'local_contact');
                        default:            // All other fields.
                            // Join array of values. Example: <select multiple>.
                            if (is_array($value)) {
                                $value = join(', ', $value);
                            }
                            // Sanitize the text.
                            $value = format_text($value, FORMAT_PLAIN, array('trusted' => false));
                            // Add to email message.
                            $htmlmessage .= '<strong>'.ucfirst($key) . ' :</strong> ' . $value . '<br>' . PHP_EOL;
						}
					}
				}
            }
        }
		
		if ($this->fromcustomform !== '') //Используем мою форму
		{
			$htmlmessage = '<ul>' . $htmlmessage . '</ul>'; // Превращаем сообщение для почты в список для улучшения читаемости
		}

        // Sanitize user agent and referer.
        $httpuseragent = format_text($_SERVER['HTTP_USER_AGENT'], FORMAT_PLAIN, array('trusted' => false));
        $httpreferer = format_text($_SERVER['HTTP_REFERER'], FORMAT_PLAIN, array('trusted' => false));

        // Prepare arrays to handle substitution of embedded tags in the footer.
        $tags = array('[fromname]', '[fromemail]', '[supportname]', '[supportemail]',
                '[lang]', '[userip]', '[userstatus]',
                '[sitefullname]', '[siteshortname]', '[siteurl]',
                '[http_user_agent]', '[http_referer]'
        );
        $info = array($from->firstname, $from->email, $CFG->supportname, $CFG->supportemail,
                current_language(), getremoteaddr(), $this->moodleuserstatus($from->email),
                $SITE->fullname . ': ', $SITE->shortname, $CFG->wwwroot,
                $httpuseragent, $httpreferer
        );

        if ($this->fromcustomform === '') //Данные о пользователе добавляем только в оригинальной версии формы
		{
		// Create the footer - Add some system information.
        $footmessage = get_string('extrainfo', 'local_contact');
        $footmessage = format_text($footmessage, FORMAT_HTML, array('trusted' => true, 'noclean' => true, 'para' => false));
        $htmlmessage .= str_replace($tags, $info, $footmessage);
		}

        // Override "from" email address if one was specified in the plugin's settings.
        $noreplyaddress = $CFG->noreplyaddress;
        if (!empty($customfrom = get_config('local_contact', 'senderaddress'))) {
            $CFG->noreplyaddress = $customfrom;
        }

        // Send email message to recipient and set replyto to the sender's email address and name.
        if (empty(get_config('local_contact', 'noreplyto')))// Not checked.
		{ 
            $status = email_to_user($to, $from, $subject, html_to_text($htmlmessage), $htmlmessage, '', '', true,
                    $from->email, $from->firstname);
					
		}
		else // Checked.
		{ 
            $status = email_to_user($to, $from, $subject, html_to_text($htmlmessage), $htmlmessage, '', '', true);
        }
		
		if (($this->fromcustomform !== '') && !empty($to_markdown)) //Если используем мою форму и нужно отправить писмо в Trello
		{
			$status_markdown = email_to_user($to_markdown, $from, $subject, html_to_text($markdown_message), $markdown_message, '', '', true,'','');
		}
		
        $CFG->noreplyaddress = $noreplyaddress;

        // If successful and a confirmation email is desired, send it the original sender.
        if ($status && $sendconfirmationemail) {
            // Substitute embedded tags for some information.
            $htmlmessage = str_replace($tags, $info, get_string('confirmationemail', 'local_contact'));
            $htmlmessage = format_text($htmlmessage, FORMAT_HTML, array('trusted' => true, 'noclean' => true, 'para' => false));

            $replyname  = empty($CFG->emailonlyfromnoreplyaddress) ? $CFG->supportname : get_string('noreplyname');
            $replyemail = empty($CFG->emailonlyfromnoreplyaddress) ? $CFG->supportemail : $CFG->noreplyaddress;
            $to = $this->makeemailuser($replyemail, $replyname);

            // Send confirmation email message to the sender.
            email_to_user($from, $to, $subject, html_to_text($htmlmessage), $htmlmessage, '', '', true);
        }
        
		return $status;
    }

    /**
     * Builds a one line status report on the user. Uses their Moodle info, if
     * logged in, or their email address to look up the information if they are
     * not.
     *
     * @param      string  $emailaddress  Plain text email address.
     *
     * @return     string  Contains what we know about the Moodle user including whether they are logged in or out.
     */
    private function moodleuserstatus($emailaddress) {
        if (isloggedin() && !isguestuser()) {
            global $USER;
            $info = get_string('fullnamedisplay', null, $USER) . ' / ' . $USER->email . ' (' . $USER->username .
                    ' / ' . get_string('eventuserloggedin', 'auth') . ')';
        } else {
            global $DB;
            $usercount = $DB->count_records('user', ['email' => $emailaddress, 'deleted' => 0]);
            switch ($usercount) {
                case 0:  // We don't know this email address.
                    $info = get_string('emailnotfound');
                    break;
                case 1: // We found exactly one match.
                    $user = get_complete_user_data('email', $emailaddress);
                    $extrainfo = '';

                    // Is user locked out?
                    if ($lockedout = get_user_preferences('login_lockout', 0, $user)) {
                        $extrainfo .= ' / ' . get_string('lockedout', 'local_contact');
                    }

                    // Has user responded to confirmation email?
                    if (empty($user->confirmed)) {
                        $extrainfo .= ' / ' . get_string('notconfirmed', 'local_contact');
                    }

                    $info = get_string('fullnamedisplay', null, $user) . ' / ' . $user->email . ' (' . $user->username .
                            ' / ' . get_string('eventuserloggedout') . $extrainfo . ')';
                    break;
                default: // We found multiple users with this email address.
                    $info = get_string('duplicateemailaddresses', 'local_contact');
            }
        }
        return $info;
    }
	
	/*
	Функция пытается найти пользователя в системе по указанной им почте.
	*/
	private function findmeuserbyemail($emailaddress)
	{
        if (isloggedin() && !isguestuser())
		{
            global $USER;
            $info = ['firstname' => $USER->firstname, 'lastname' => $USER->lastname, 'username' => $USER->username, 'userstatus' => get_string('eventuserloggedin', 'auth')];
        }
		else
		{
            global $DB;
			
			$usercount = $DB->count_records('user', ['email' => $emailaddress, 'deleted' => 0]); // Считаем, сколько в таблице БД 'user' записей, в который указанная пользователем почта и одновременно нет отметки об удалении пользователя.
            
			switch ($usercount)
			{                
				case 0: // Если не нашли почту, выдаем массив с тремя 'usernotfound' в строках и сообщение о том, что пользователь с такой почтой не найден
                    $info = ['firstname' => 'usernotfound', 'lastname' => 'usernotfound', 'username' => 'usernotfound', 'userstatus' => get_string('emailnotfound')];
					 break;
					 
				case 1: // Если нашли едиснтвенного пользователя, помещаем в массив его данные и сообщение о том, что пользователь разлогинен
				    $user = get_complete_user_data('email', $emailaddress); // В переменную $user помещаются данные о пользователе
                    $info = ['firstname' => $user->firstname, 'lastname' => $user->lastname, 'username' => $user->username, 'userstatus' => get_string('eventuserloggedout')];
                    break;
					
				default: //Если нашли несколько пользователей с такой почтой, помещаем в массив строки с 'multipleusers' cообщение о том, что пользователей с такой почтой несколько
                    $info = ['firstname' => 'multipleusers', 'lastname' => 'multipleusers', 'username' => 'multipleusers', 'userstatus' => get_string('duplicateemailaddresses', 'local_contact')];
            }
        }
        return $info;
    }
	
	
	/*
	Функция пытается найти пользователя в системе по указанному им логину.
	*/
	private function findmeuserbylogin($userlogin)
	{
        if (isloggedin() && !isguestuser())
		{
            global $USER;
            $info = ['firstname' => $USER->firstname, 'lastname' => $USER->lastname, 'username' => $USER->username, 'userstatus' => get_string('eventuserloggedin', 'auth')];
        }
		else
		{
            global $DB;
			$usercount = $DB->count_records('user', ['username' => $userlogin, 'deleted' => 0]); // Считаем, сколько в таблице БД 'user' записей, в который указанный пользователем логин и одновременно нет отметки об удалении пользователя.
            
			switch ($usercount)
			{
                case 0:  // Если не нашли логин, выдаем массив с тремя 'usernotfound' в строках и сообщение о том, что пользователь с таким логином не найден
                    $info = ['firstname' => 'usernotfound', 'lastname' => 'usernotfound', 'username' => 'usernotfound', 'userstatus' => get_string('loginnotfound','local_contact')];
					 break;
					 
				// Если нашли едиснтвенного пользователя, помещаем в массив его данные и сообщение о том, что пользователь разлогинен
                case 1:
				    $user = get_complete_user_data('username', $userlogin); // В переменную $user помещаются данные о пользователе
                    $info = ['firstname' => $user->firstname, 'lastname' => $user->lastname, 'username' => $user->username, 'email' => $user->email,'userstatus' => get_string('eventuserloggedout')];
                    break;			
            }
        }
        return $info;
    }
	
	
	
	
	/*Функция для проверки совпадения двух строк.
	Возвращает true, если строки имеют одинаковую длину и одна строка является подстрокой другой строки.
	Возвращает false, если строки имеют разную длину или строки имеют одинаковую длину, но одна строка не является подстрокой другой.*/
	private function compmestr($str1, $str2)
	{
		$stringsAreSame = true;
		
		if (mb_strlen($str1) === mb_strlen($str2))
		{
			if(mb_stripos($str2,$str1) !== 0)
			{
				$stringsAreSame = false;
			}
		}
		else
		{
			$stringsAreSame = false;
		}
		
		return $stringsAreSame;
	}
}
