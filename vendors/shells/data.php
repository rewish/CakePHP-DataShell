<?php
/**
 * Data Shell
 */
App::import('Core', 'File', false);

class DataShell extends Shell
{
	protected $directory;
	protected $connection = 'default';

	public function startup()
	{
		parent::startup();

		if (!empty($this->params['directory'])) {
			$this->directory = $this->params['directory'];
		} else {
			$this->directory = APP . 'config' . DS . 'schema' . DS . 'data';
		}
		$this->directory .= DS;

		if (!empty($this->params['connection'])) {
			$connection = $this->params['connection'];
		}

		if (empty($this->params['name']) && !empty($this->args[0])) {
			$this->params['name'] = $this->args[0];
		}
	}

	public function main()
	{
		$this->help();
	}

	public function help()
	{
		$help = <<<TEXT
Usage: 
	cake data export <name>
	cake data import <name>
TEXT;
		$this->out($help);
		$this->_stop();
	}

	public function export()
	{
		if (!empty($this->params['name'])) {
			$modelNames = array(Inflector::camelize(Inflector::singularize($this->params['name'])));
		} else {
			$modelNames = App::objects('model');
		}

		foreach ($modelNames as $modelName) {
			$Model = ClassRegistry::init($modelName);
			$Model->useDbConfig = $this->connection;

			$records = $Model->find('all', array('recursive' => -1, 'callbacks' => false));

			if (empty($records)) {
				continue;
			}

			$content = "<?php\n";
			$content .= "class {$Model->name}Data\n{\n";
			$content .= "\tpublic \$name = '{$Model->name}';\n\n";
			$content .= "\tpublic \$records = array(\n";

			foreach ($records as $record) {
				$content .= "\t\tarray(\n";
				foreach ($record[$Model->alias] as $field => $value) {
					if (is_null($value)) {
						$content .= "\t\t\t'$field' => null,\n";
					} else {
						$content .= "\t\t\t'$field' => '$value',\n";
					}
				}
				$content .= "\t\t),\n";
			}
			$content .= "\t);\n";
			$content .= '}';

			$filePath = $this->directory . Inflector::underscore($Model->name) . '_data.php';
			$file = new File($filePath, true);
			$file->write($content);

			$this->out('Data exported: ' . $Model->name);
		}
	}

	public function import()
	{
		if (isset($this->params['name'])) {
			$dataObjects = array(Inflector::camelize(Inflector::singularize($this->params['name'])) . 'Data');
		} else {
			$dataObjects = App::objects('class', $this->directory);
		}

		$passFields = null;
		if (array_key_exists('pass', $this->params)) {
			$passFields = array('created', 'updated', 'modified');
		}

		foreach ($dataObjects as $data) {
			App::import('class', $data, false, $this->directory);
			extract(get_class_vars($data));

			if (empty($records) || !is_array($records)) {
				continue;
			}

			$Model = ClassRegistry::init($name);
			$Model->useDbConfig = $this->connection;
			if ($passFields) {
				foreach($records as &$record) {
					foreach ($passFields as $field) {
						unset($record[$field]);
					}
				}
			}
			$Model->query("TRUNCATE `$Model->table`");

			$success = 'Faild';
			if ($Model->saveAll($records, array('validate' => false))) {
				$success = 'Success';
			}

			$this->out("Data imported: $Model->name [$success]");
		}
	}
}