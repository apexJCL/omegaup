<?php

/**
 * 
 * Please read full (and updated) documentation at: 
 * https://github.com/omegaup/omegaup/wiki/Arena 
 *
 * 
 * POST /contests/:id
 * Si el usuario puede verlos, muestra los detalles del concurso :id.
 *
 * */

require_once("ApiHandler.php");

class ShowContest extends ApiHandler
{    
    
    protected function RegisterValidatorsToRequest()
    {
        ValidatorFactory::stringNotEmptyValidator()->addValidator(new CustomValidator(
                function ($value)
                {
                    // Check if the contest exists
                    return ContestsDAO::getByAlias($value);
                }, "Contest is invalid."))
            ->validate(RequestContext::get("alias"), "alias");
        
        // If the contest is private, verify that our user is invited                
        $contest = ContestsDAO::getByAlias(RequestContext::get("alias"));                                
        if ($contest->getPublic() === '0')            
        {      
            try
            {
                if (is_null(ContestsUsersDAO::getByPK($this->_user_id, $contest->getContestId())))
                {
                    throw new ApiException(ApiHttpErrors::forbiddenSite());
                }
            }
            catch(ApiException $e)
            {
                // Propagate exception
                throw $e;
            }
            catch(Exception $e)
            {
                 // Operation failed in the data layer
                 throw new ApiException( ApiHttpErrors::invalidDatabaseOperation() );                
            }
        }                                                
    }      


    protected function GenerateResponse() 
    {
       // Create array of relevant columns
        $relevant_columns = array("title", "description", "start_time", "finish_time", "window_length", "alias", "scoreboard", "points_decay_factor", "partial_score", "submissions_gap", "feedback", "penalty", "time_start", "penalty_time_start", "penalty_calc_policy");
        
        // Get our contest given the alias
        try
        {            
            $contest = ContestsDAO::getByAlias(RequestContext::get("alias"));
        }
        catch(Exception $e)
        {
            // Operation failed in the data layer
           throw new ApiException( ApiHttpErrors::invalidDatabaseOperation() );                
        }
        
        // Add the contest to the response
        $this->addResponseArray($contest->asFilteredArray($relevant_columns));                    
        
        // Get problems of the contest
        $key_problemsInContest = new ContestProblems(
            array("contest_id" => $contest->getContestId()));        
        try
        {
            $problemsInContest = ContestProblemsDAO::search($key_problemsInContest);
        }
        catch(Exception $e)
        {
            // Operation failed in the data layer
           throw new ApiException( ApiHttpErrors::invalidDatabaseOperation());        
        }        
        
        // Add info of each problem to the contest
        $problemsResponseArray = array();

        // Set of columns that we want to show through this API. Doesn't include the SOURCE
        $relevant_columns = array("title", "alias", "validator", "time_limit", "memory_limit", "visits", "submissions", "accepted", "dificulty", "order");
        
        foreach($problemsInContest as $problemkey)
        {
            try
            {
                // Get the data of the problem
                $temp_problem = ProblemsDAO::getByPK($problemkey->getProblemId());
            }
            catch(Exception $e)
            {
                // Operation failed in the data layer
               throw new ApiException( ApiHttpErrors::invalidDatabaseOperation() );        
            }
            
            // Add the 'points' value that is stored in the ContestProblem relationship
            $temp_array = $temp_problem->asFilteredArray($relevant_columns);
            $temp_array["points"] = $problemkey->getPoints();
                    
            // Save our array into the response
            array_push($problemsResponseArray, $temp_array);
            
        }
        
        // Save the time of the first access
        try
        {
            $contest_user = ContestsUsersDAO::CheckAndSaveFirstTimeAccess(
                    $this->_user_id, $contest->getContestId());
        }
        catch(Exception $e)
        {
             // Operation failed in the data layer
             throw new ApiException( ApiHttpErrors::invalidDatabaseOperation() );        
        }
        
        // Set response
        $this->addResponse("problems", $problemsResponseArray);
                
        // @TODO Add mini ranking here
    }    
}

?>
