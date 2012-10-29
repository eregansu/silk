<?php

/* Copyright 2012 Mo McRoberts.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

class BuiltinSilkModuleInstall extends BuiltinModuleInstaller
{
	public function canCoexistWithSoleWebModule()
	{
		return true;
	}

	public function writeAppConfig($file, $isSoleWebModule = false, $chosenSoleWebModule = null)
	{
		fwrite($file, "\$CLI_ROUTES['silk'] = array('file' => PLATFORM_ROOT . 'silk/app.php', 'class' => 'Silk', 'fromRoot' => true);\n");
	}
}
