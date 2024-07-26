pipeline {
  agent any
  parameters {
        string(name: 'BINARY_STORE', defaultValue: '/Binaries', trim: true)
  }
  stages {
    stage('Extract Sources') {
      steps {
	    // Use the master branch to get the sources. Ensure the media is attached into the pi.
        dir('pkg_ra_data_retention') {
          // Checkout to the right directory
	      //git(url: 'https://github.com/keithgrimes/pkg_ra_data_retention', branch: 'main')
	      //git(url: 'https://github.com/keithgrimes/pkg_ra_data_retention')
		}
      }
    }
    stage('Update version information') {
      steps {
            sh 'python2 /home/UpdateJoomlaBuild -bx -i packages/com_ra_data_retention/com_ra_data_retention.xml'
            sh 'python2 /home/UpdateJoomlaBuild -bx -i packages/plg_dataretention/dataretention.xml'
            sh 'python2 /home/UpdateJoomlaBuild -bx -i pkg_ra_data_retention.xml'
      }
    }
    stage('Package Zip File') {
      steps {
        // First tidy the directory
        //sh 'rm -r packages'
        //sh 'rm pkg_ra_data_retention.xml'
        // First Zip the components as part of the package
        //sh 'rm -r .git'
        dir('packages') {
          sh 'zip -r com_ra_data_retention.zip com_ra_data_retention'
		      sh 'rm -r com_ra_data_retention'

          sh 'zip -r plg_dataretention.zip plg_dataretention'
		      sh 'rm -r plg_dataretention'
		    } 

		    dir('pkg_ra_data_retention') {
          sh 'pwd'
          sh 'ls -al'
//          sh 'rm -r .git'
		      // Now zip the main package
          sh 'zip -r pkg_ra_data_retention.zip .'
        }
      }
    }

    stage('Repository Store') {
    	steps {
//    	  script {
//    	      dir('tmp'){
//    	        sh 'rm -f *.zip'
//    	      }
//         }
//         sh 'python2 /home/UpdateJoomlaBuild -bx -i pkg_ra_data_retention/pkg_ra_data_retention.xml -z tmp' 
          fileOperations([fileCopyOperation(excludes: '', flattenFiles: true, includes: '*.zip', targetLocation: params.BINARY_STORE)])
    	}
    }
  } // End of Stages
  post {
  	always {
  	    echo "Completed"
  	}
  	success {
  		echo "Completed Succcessfully"
  		cleanWs()
  	}
  	failure {
  	    echo "Completed with Failure"
  	}
  	unstable {
  	    echo "Unstable Build"
  	}
  	changed {
  	    echo "Compeleted with Changes"
  	}
  } // End of Post
} // End of Pipeline
