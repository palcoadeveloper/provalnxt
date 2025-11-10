 <!-- Modal -->
 <div class="modal fade bd-example-modal-lg" id="startValidationModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h4 class="modal-title" id="myLargeModalLabel">Begin Validation</h4>
                    <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                    <div class="col-lg-12 grid-margin stretch-card">
                      <div class="card">
                        <div class="card-body">
                          <h4 class="card-title">Need some details</h4>
                          <form id="formmodalbeginvalidation" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <table class="table table-bordered">
                              <tr>
                                <td>
                                  <h6 class="text-muted">Validation Workflow ID</h6>
                                </td>
                                <td colspan="3">
                                  <div id="modalvalwfid"></div>
                                </td>
                              </tr>

                              <tr>
                                <td>
                                  <h6 class="text-muted">Training Requirement</h6>
                                </td>
                                <td colspan="3">
                                  <!-- Dynamic file input and dropdowns container -->
                                  <div id="dynamicInputsContainer"></div>
                                  <button class="btn btn-gradient-success btn-icon-text btn-sm" type="button" id="addEntryBtn"><i class="mdi mdi-plus-circle"></i> Add Training Details</button>
                                </td>
                              </tr>

                              <tr class="dev_remarks">
                                <td>
                                  <h6 class="text-muted">Deviation Remarks</h6>
                                </td>
                                <td colspan="3"><input type="text" class="form-control" id="deviationremark" placeholder="Provide Deviation Remarks: Validation study started after the due date." maxlength="500" required> </td>
                              </tr>

                              <tr>
                                <td>
                                  <h6 class="text-muted">Justification for selection of system</h6>
                                </td>
                                <td colspan="3"> <textarea class="form-control" id="justification" placeholder="Provide justification for the selection of the system." maxlength="500" required></textarea> </td>
                              </tr>

                              <tr>
                                <td class="align-top">
                                  <h6 class="text-muted">SOPs and MMs</h6>
                                </td>
                                <td colspan="3">
                                  <!-- SOP input fields -->
                                  <div class="form-group">
                                    <label for="sop1">SOP for operating the HVAC System</label>
                                    <input type="text" class="form-control" id="sop1" placeholder="Enter SOP/Document number and version, or specify 'NA' if not applicable." maxlength="200" required>
                                  </div>
                                  <!-- More SOP fields -->
                                  <div class="form-group">
                                    <label for="sop2">SOP for recording pressure difference with respect to adjacent area / atmosphere.</label>
                                    <input type="text" class="form-control" id="sop2" placeholder="Enter SOP/Document number and version, or specify 'NA' if not applicable." maxlength="200" required>
                                  </div>
                                  <!-- More SOP fields here -->
                                  <div class="form-group">
                                    <label for="sop3">SOP for Air velocity measurement and calculation of number of air changes</label>
                                    <input type="text" class="form-control" id="sop3" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop4">SOP for checking installed filter system leakages</label>
                                    <input type="text" class="form-control" id="sop4" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop5">SOP for checking of particulate matter count.</label>
                                    <input type="text" class="form-control" id="sop5" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop6">SOP for airflow direction test and visualization.</label>
                                    <input type="text" class="form-control" id="sop6" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop7">SOP for BMS start stop operation. (if applicable)</label>
                                    <input type="text" class="form-control" id="sop7" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop8">SOP for Duct leakage Measurement.</label>
                                    <input type="text" class="form-control" id="sop8" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop9">SOP for area recovery / clean up period study.</label>
                                    <input type="text" class="form-control" id="sop9" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop10">SOP for containment leakage test.</label>
                                    <input type="text" class="form-control" id="sop10" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop11">SOP scrubber / Point exhaust CFM</label>
                                    <input type="text" class="form-control" id="sop11" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop12">Microbiological method (MM) for environmental monitoring</label>
                                    <input type="text" class="form-control" id="sop12" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                  <div class="form-group">
                                    <label for="sop13">Additional SOP Details</label>
                                    <input type="text" class="form-control" id="sop13" placeholder="SOP/Document No. Including Version No." maxlength="200" required>

                                  </div>
                                </td>
                              </tr>

                              <tr>
                                <td colspan="4">
                                  <div class="d-flex justify-content-center">
                                    <button id="btnSubmitData" class='btn btn-gradient-primary'><i class="mdi mdi-check-circle"></i> Start Validation Study</button>
                                    
                                </div>
                                </td>
                              </tr>
                            </table>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>