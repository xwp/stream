load File.join(
  File.dirname(__FILE__),
  "vendor/wpsh/local/Vagrantfile"
)

Vagrant.configure(2) do |config|
	config.vm.hostname = "stream"

	# Wait 10 seconds before the docker containers are up.
	config.vm.provision "shell",
		inline: "sleep 10",
		run: "always"

	# Setup the WP sites.
	config.vm.provision "shell",
		path: "local/vagrant/setup-wp.sh",
		run: "always",
		env: {
			"DOCKER_COMPOSE_FILE" => "/vagrant/docker-compose.yml"
		}
end
