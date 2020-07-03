load File.join(
  File.dirname(__FILE__),
  "vendor/wpsh/local/Vagrantfile"
)

Vagrant.configure(2) do |config|
	config.vm.hostname = "stream"

	# Setup the WP sites.
	config.vm.provision "shell",
		inline: "docker-compose -f /vagrant/docker-compose.yml exec -T --user $(id -u) wordpress xwp_wait mysql:3306 -t 60 -- wp core multisite-install --url=stream.local",
		run: "always"
end
